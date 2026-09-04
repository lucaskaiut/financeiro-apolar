<?php

namespace App\Modules\Assistant\Services;

use App\Modules\Assistant\Enums\MessageRole;
use App\Modules\Assistant\Http\Resources\MessageResource;
use App\Modules\Assistant\Models\Conversation;
use App\Modules\Assistant\Models\Message;
use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AssistantService
{
    private const MAX_TOOL_ITERATIONS = 6;

    private const HISTORY_LIMIT = 40;

    public function __construct(
        private readonly AiConfigService $config,
        private readonly OpenAiClient $client,
        private readonly AssistantTools $tools,
    ) {}

    public function stream(Conversation $conversation, User $user, string $content): StreamedResponse
    {
        $tenant = TenantContext::tenant();

        $config = $this->config->resolve($tenant);
        $registry = $this->tools->build($user);

        $this->recordUserMessage($conversation, $content);

        $history = $this->history($conversation);
        $system = $this->buildSystemPrompt($config);

        return response()->stream(function () use ($conversation, $config, $registry, $history, $system): void {
            $this->run($conversation, $config, $registry, $history, $system);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int, system_prompt: ?string}  $config
     * @param  list<array<string, mixed>>  $history
     */
    private function run(Conversation $conversation, array $config, ToolRegistry $registry, array $history, string $system): void
    {
        $emit = function (string $type, array $data = []): void {
            echo 'data: '.json_encode(['type' => $type, ...$data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }

            flush();
        };

        $messages = [['role' => 'system', 'content' => $system], ...$history];
        $definitions = $registry->definitions();

        try {
            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $result = $this->client->streamChat(
                    $config,
                    $messages,
                    $definitions,
                    fn (string $delta) => $emit('delta', ['content' => $delta]),
                );

                if ($result['tool_calls'] === []) {
                    $message = $this->recordAssistantMessage($conversation, $result['content']);
                    $emit('done', ['message' => MessageResource::make($message)->resolve()]);

                    return;
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $result['content'] !== '' ? $result['content'] : null,
                    'tool_calls' => $this->openAiToolCalls($result['tool_calls']),
                ];

                $this->recordAssistantToolCalls($conversation, $result['tool_calls']);

                $emit('tool', [
                    'calls' => array_map(
                        fn (array $call) => ['name' => $call['name'], 'arguments' => $call['arguments']],
                        $result['tool_calls'],
                    ),
                ]);

                foreach ($result['tool_calls'] as $call) {
                    $execution = $this->executeTool($registry, $call);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'],
                        'name' => $call['name'],
                        'content' => json_encode($execution, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    ];

                    $this->recordToolResult($conversation, $call, $execution);

                    $emit('tool_result', ['name' => $call['name'], 'ok' => $execution['ok']]);
                }
            }

            $emit('error', ['message' => 'O assistente excedeu o limite de operações. Reformule sua pergunta.']);
        } catch (Throwable $e) {
            $this->recordAssistantMessage($conversation, 'Não consegui concluir a operação: '.$e->getMessage());
            $emit('error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param  array{id: string, name: string, arguments: array<string, mixed>}  $call
     * @return array{ok: bool, result: mixed, error: ?string}
     */
    private function executeTool(ToolRegistry $registry, array $call): array
    {
        try {
            $tool = $registry->get($call['name']);

            return ['ok' => true, 'result' => $tool->run($call['arguments']), 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array{system_prompt: ?string}  $config
     */
    private function buildSystemPrompt(array $config): string
    {
        $base = <<<'TXT'
Você é o assistente financeiro do sistema, atuando como consultor financeiro da organização.

Regras fundamentais:
- Responda SEMPRE em português do Brasil.
- Você NÃO tem acesso direto ao banco de dados: toda informação deve ser obtida pelas ferramentas disponíveis.
- NUNCA invente valores nem assuma dados que não foram retornados por uma ferramenta.
- Formate valores monetários em reais (R$) e datas no formato brasileiro (dd/mm/aaaa).
- Para criar qualquer lançamento (despesa, receita, parcelamento ou recorrência), solicite e obtenha a confirmação explícita do usuário antes de executar a ferramenta.
- NUNCA execute operações destrutivas sem confirmação explícita.
- Se uma ferramenta falhar ou não estiver disponível, explique o problema com clareza.
- Ajude a explicar indicadores, gerar análises, identificar riscos, encontrar inconsistências e auxiliar na conciliação bancária.
- Ao listar valores, prefira tabelas Markdown quando houver vários itens.
TXT;

        if (filled($config['system_prompt'] ?? null)) {
            $base .= "\n\nInstruções adicionais da organização:\n".$config['system_prompt'];
        }

        return $base;
    }

    private function recordUserMessage(Conversation $conversation, string $content): void
    {
        $first = $conversation->messages()->count() === 0;

        Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'role' => MessageRole::User,
            'content' => $content,
        ]);

        if ($first || $conversation->title === 'Nova conversa') {
            $conversation->title = Str::limit(trim(preg_replace('/\s+/', ' ', $content) ?? 'Nova conversa'), 60, '');
            $conversation->save();
        }
    }

    /**
     * @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $calls
     */
    private function recordAssistantToolCalls(Conversation $conversation, array $calls): void
    {
        Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'role' => MessageRole::Assistant,
            'content' => '',
            'tool_calls' => $calls,
        ]);
    }

    /**
     * @param  array{id: string, name: string, arguments: array<string, mixed>}  $call
     * @param  array{ok: bool, result: mixed, error: ?string}  $execution
     */
    private function recordToolResult(Conversation $conversation, array $call, array $execution): void
    {
        Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'role' => MessageRole::Tool,
            'content' => json_encode($execution, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'tool_results' => [['id' => $call['id'], 'name' => $call['name'], 'arguments' => $call['arguments']]],
        ]);
    }

    private function recordAssistantMessage(Conversation $conversation, string $content): Message
    {
        return Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'role' => MessageRole::Assistant,
            'content' => $content,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->sortBy('id')
            ->values();

        $history = [];

        foreach ($messages as $message) {
            $entry = match ($message->role) {
                MessageRole::User => ['role' => 'user', 'content' => $message->content],
                MessageRole::Assistant => $this->assistantHistoryEntry($message),
                MessageRole::Tool => $this->toolHistoryEntry($message),
                default => null,
            };

            if ($entry !== null) {
                $history[] = $entry;
            }
        }

        return $history;
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantHistoryEntry(Message $message): array
    {
        $calls = $message->tool_calls ?? [];

        if ($calls === []) {
            return ['role' => 'assistant', 'content' => $message->content];
        }

        return [
            'role' => 'assistant',
            'content' => $message->content !== '' ? $message->content : null,
            'tool_calls' => $this->openAiToolCalls($calls),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toolHistoryEntry(Message $message): ?array
    {
        $meta = ($message->tool_results ?? [])[0] ?? null;

        if ($meta === null) {
            return null;
        }

        return [
            'role' => 'tool',
            'tool_call_id' => $meta['id'],
            'name' => $meta['name'],
            'content' => $message->content,
        ];
    }

    /**
     * @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $calls
     * @return list<array{id: string, type: string, function: array{name: string, arguments: string}}>
     */
    private function openAiToolCalls(array $calls): array
    {
        return array_map(fn (array $call) => [
            'id' => $call['id'],
            'type' => 'function',
            'function' => [
                'name' => $call['name'],
                'arguments' => json_encode($call['arguments'] ?? [], JSON_UNESCAPED_UNICODE),
            ],
        ], $calls);
    }
}
