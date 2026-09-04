function cloneWithInlineStyles(source: HTMLElement): HTMLElement {
  const clone = source.cloneNode(true) as HTMLElement

  const walk = (src: Element, dest: Element) => {
    if (src instanceof HTMLElement && dest instanceof HTMLElement) {
      const computed = window.getComputedStyle(src)

      for (let index = 0; index < computed.length; index += 1) {
        const property = computed.item(index)
        dest.style.setProperty(property, computed.getPropertyValue(property), computed.getPropertyPriority(property))
      }
    }

    for (let index = 0; index < src.children.length; index += 1) {
      walk(src.children[index], dest.children[index])
    }
  }

  walk(source, clone)
  return clone
}

async function elementToPngBlob(element: HTMLElement): Promise<Blob> {
  const width = element.offsetWidth
  const height = element.offsetHeight

  if (width === 0 || height === 0) {
    throw new Error('Elemento sem dimensões visíveis')
  }

  const clone = cloneWithInlineStyles(element)
  clone.setAttribute('xmlns', 'http://www.w3.org/1999/xhtml')

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
    <foreignObject width="100%" height="100%">
      ${new XMLSerializer().serializeToString(clone)}
    </foreignObject>
  </svg>`

  const image = new Image()
  const svgUrl = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`

  await new Promise<void>((resolve, reject) => {
    image.onload = () => resolve()
    image.onerror = () => reject(new Error('Falha ao renderizar imagem'))
    image.src = svgUrl
  })

  const scale = 2
  const canvas = document.createElement('canvas')
  canvas.width = width * scale
  canvas.height = height * scale

  const context = canvas.getContext('2d')

  if (!context) {
    throw new Error('Canvas não suportado')
  }

  context.scale(scale, scale)
  context.drawImage(image, 0, 0)

  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob(resolve, 'image/png')
  })

  if (!blob) {
    throw new Error('Falha ao gerar imagem')
  }

  return blob
}

export async function captureElementToClipboard(element: HTMLElement): Promise<void> {
  const blob = await elementToPngBlob(element)

  if (!navigator.clipboard?.write) {
    throw new Error('Área de transferência indisponível neste navegador')
  }

  await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })])
}
