import useEmblaCarousel from 'embla-carousel-react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useCallback, useEffect, useState, type ReactNode } from 'react'

interface CarouselProps {
  label: string
  children: ReactNode[]
  onReachEnd?: () => void
  large?: boolean
}

export const CAROUSEL_GUTTER = 'px-4 sm:px-8 lg:px-12'

export function Carousel({ label, children, onReachEnd, large }: CarouselProps) {
  const [viewport, api] = useEmblaCarousel({ align: 'start', dragFree: true, containScroll: 'trimSnaps', slidesToScroll: 'auto' })
  const [canPrevious, setCanPrevious] = useState(false)
  const [canNext, setCanNext] = useState(false)

  const update = useCallback(() => {
    if (!api) return
    setCanPrevious(api.canScrollPrev())
    setCanNext(api.canScrollNext())
    if (!api.canScrollNext()) onReachEnd?.()
  }, [api, onReachEnd])

  useEffect(() => {
    if (!api) return
    update()
    api.on('select', update).on('reInit', update).on('settle', update)
    return () => {
      api.off('select', update).off('reInit', update).off('settle', update)
    }
  }, [api, update])

  const arrow = 'absolute inset-y-0 z-10 hidden w-10 place-items-center bg-black/55 text-white opacity-0 transition group-hover/row:opacity-100 focus-visible:opacity-100 sm:grid lg:w-12'

  return (
    <div className="group/row relative" role="group" aria-label={label}>
      <div ref={viewport} className={`overflow-hidden ${CAROUSEL_GUTTER}`}>
        <div className="flex touch-pan-y gap-3">
          {children.map((child, index) => (
            <div key={index} className={large ? 'min-w-0 flex-[0_0_62%] sm:flex-[0_0_38%] md:flex-[0_0_28%] lg:flex-[0_0_22%] 2xl:flex-[0_0_18%]' : 'min-w-0 flex-[0_0_42%] sm:flex-[0_0_26%] md:flex-[0_0_20%] lg:flex-[0_0_15.5%] 2xl:flex-[0_0_12.5%]'}>
              {child}
            </div>
          ))}
        </div>
      </div>
      {canPrevious && (
        <button type="button" aria-label={`Voltar em ${label}`} onClick={() => api?.scrollPrev()} className={`${arrow} left-0`}>
          <ChevronLeft className="size-7" />
        </button>
      )}
      {canNext && (
        <button type="button" aria-label={`Avançar em ${label}`} onClick={() => api?.scrollNext()} className={`${arrow} right-0`}>
          <ChevronRight className="size-7" />
        </button>
      )}
    </div>
  )
}
