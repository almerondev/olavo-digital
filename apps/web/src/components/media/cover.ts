export function coverSrcSet(urls: Record<string, string>): string | undefined {
  const entries = Object.entries(urls)
  return entries.length ? entries.map(([width, url]) => `${url} ${width}w`).join(', ') : undefined
}

export function coverSrc(urls: Record<string, string>): string | undefined {
  return Object.values(urls)[0]
}
