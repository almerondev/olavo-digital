import { useEffect, useRef, useState } from 'react'

/** Fica verdadeiro na primeira vez que o elemento se aproxima da tela. */
export function useInView<T extends Element>(rootMargin = '400px') {
  const ref = useRef<T>(null)
  const [inView, setInView] = useState(false)

  useEffect(() => {
    const element = ref.current
    if (!element || inView) return

    const observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) setInView(true)
    }, { rootMargin })

    observer.observe(element)
    return () => observer.disconnect()
  }, [inView, rootMargin])

  return { ref, inView }
}
