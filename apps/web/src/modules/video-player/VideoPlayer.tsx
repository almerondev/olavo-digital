import { Captions, Check, Maximize, Minimize, Pause, Play, RotateCcw, SkipForward, Volume2, VolumeX, X } from 'lucide-react'
import { useCallback, useEffect, useRef, useState, type ChangeEvent } from 'react'
import { Link } from 'react-router'
import { cn } from '@/lib/utils'
import type { CardMaterial, PlaybackSource } from '@/types/api'
import { useProgressReporter } from './useProgressReporter'

const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2]
const CONTROLS_HIDE_MS = 3000
const SEEK_STEP = 10

interface QualityLevel {
  index: number
  label: string
}

interface HlsHandle {
  currentLevel: number
  destroy: () => void
}

interface VideoPlayerProps {
  source: PlaybackSource
  title: string
  slug: string
  saveProgress: boolean
  related: CardMaterial[]
  onClose: () => void
  onComplete: () => void
}

function formatTime(seconds: number): string {
  if (!Number.isFinite(seconds)) return '0:00'
  const total = Math.floor(seconds)
  const hours = Math.floor(total / 3600)
  const minutes = Math.floor((total % 3600) / 60)
  const rest = String(total % 60).padStart(2, '0')

  return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`
}

export function VideoPlayer({ source, title, slug, saveProgress, related, onClose, onComplete }: VideoPlayerProps) {
  const root = useRef<HTMLDivElement>(null)
  const video = useRef<HTMLVideoElement>(null)
  const hls = useRef<HlsHandle | null>(null)
  const hideTimer = useRef<number | undefined>(undefined)

  const [playing, setPlaying] = useState(false)
  const [time, setTime] = useState(0)
  const [duration, setDuration] = useState(0)
  const [volume, setVolume] = useState(1)
  const [muted, setMuted] = useState(false)
  const [speed, setSpeed] = useState(1)
  const [fullscreen, setFullscreen] = useState(false)
  const [captions, setCaptions] = useState(false)
  const [levels, setLevels] = useState<QualityLevel[]>([])
  const [quality, setQuality] = useState(-1)
  const [controlsVisible, setControlsVisible] = useState(true)
  const [ended, setEnded] = useState(false)
  const [completed, setCompleted] = useState(source.completed)
  const [failed, setFailed] = useState(false)

  useProgressReporter(video, slug, saveProgress)

  useEffect(() => {
    const element = video.current
    if (!element) return

    let cancelled = false

    if (source.kind === 'hls' && !element.canPlayType('application/vnd.apple.mpegurl')) {
      import('hls.js').then(({ default: Hls }) => {
        if (cancelled || !Hls.isSupported()) return

        const instance = new Hls()
        hls.current = instance
        instance.loadSource(source.url)
        instance.attachMedia(element)
        instance.on(Hls.Events.MANIFEST_PARSED, (_event, data) => {
          setLevels(data.levels.map((level, index) => ({ index, label: level.height ? `${level.height}p` : `Nível ${index + 1}` })))
        })
        instance.on(Hls.Events.ERROR, (_event, data) => {
          if (data.fatal) setFailed(true)
        })
      })
    } else {
      element.src = source.url
    }

    return () => {
      cancelled = true
      hls.current?.destroy()
      hls.current = null
    }
  }, [source.kind, source.url])

  const toggle = useCallback(() => {
    const element = video.current
    if (!element) return

    if (element.paused) element.play().catch(() => setPlaying(false))
    else element.pause()
  }, [])

  const revealControls = useCallback(() => {
    setControlsVisible(true)
    window.clearTimeout(hideTimer.current)
    hideTimer.current = window.setTimeout(() => {
      if (!video.current?.paused) setControlsVisible(false)
    }, CONTROLS_HIDE_MS)
  }, [])

  const toggleFullscreen = useCallback(() => {
    if (document.fullscreenElement) document.exitFullscreen().catch(() => undefined)
    else root.current?.requestFullscreen().catch(() => undefined)
  }, [])

  useEffect(() => {
    const onChange = () => setFullscreen(document.fullscreenElement !== null)
    document.addEventListener('fullscreenchange', onChange)
    return () => document.removeEventListener('fullscreenchange', onChange)
  }, [])

  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      const element = video.current
      if (!element || (event.target instanceof HTMLElement && ['INPUT', 'SELECT'].includes(event.target.tagName))) return

      if (event.key === ' ' || event.key === 'k') {
        event.preventDefault()
        toggle()
      } else if (event.key === 'ArrowRight') {
        element.currentTime += SEEK_STEP
      } else if (event.key === 'ArrowLeft') {
        element.currentTime -= SEEK_STEP
      } else if (event.key === 'f') {
        toggleFullscreen()
      } else if (event.key === 'm') {
        setMuted((value) => !value)
      } else if (event.key === 'Escape' && !document.fullscreenElement) {
        onClose()
      }

      revealControls()
    }

    window.addEventListener('keydown', onKey)
    const overflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      window.removeEventListener('keydown', onKey)
      document.body.style.overflow = overflow
      window.clearTimeout(hideTimer.current)
    }
  }, [onClose, revealControls, toggle, toggleFullscreen])

  useEffect(() => {
    const element = video.current
    if (!element) return

    element.volume = volume
    element.muted = muted
    element.playbackRate = speed
  }, [muted, speed, volume])

  useEffect(() => {
    const track = video.current?.textTracks[0]
    if (track) track.mode = captions ? 'showing' : 'hidden'
  }, [captions])

  function seek(event: ChangeEvent<HTMLInputElement>) {
    if (video.current) video.current.currentTime = Number(event.target.value)
  }

  function changeQuality(event: ChangeEvent<HTMLSelectElement>) {
    const level = Number(event.target.value)
    setQuality(level)
    if (hls.current) hls.current.currentLevel = level
  }

  function markCompleted() {
    setCompleted(true)
    onComplete()
  }

  function restart() {
    if (!video.current) return
    video.current.currentTime = 0
    setEnded(false)
    video.current.play().catch(() => undefined)
  }

  const button = 'grid size-9 shrink-0 place-items-center rounded transition hover:bg-white/15'
  const select = 'h-8 rounded border border-white/30 bg-black/60 px-1 text-xs text-white'
  const showControls = controlsVisible || !playing

  return (
    <div
      ref={root}
      role="dialog"
      aria-modal="true"
      aria-label={title}
      onMouseMove={revealControls}
      onTouchStart={revealControls}
      className={cn('dark fixed inset-0 z-[60] bg-black text-white', !showControls && 'cursor-none')}
    >
      <video
        ref={video}
        crossOrigin="anonymous"
        playsInline
        autoPlay
        onClick={toggle}
        onPlay={() => {
          setPlaying(true)
          setEnded(false)
          revealControls()
        }}
        onPause={() => setPlaying(false)}
        onEnded={() => setEnded(true)}
        onTimeUpdate={(event) => setTime(event.currentTarget.currentTime)}
        onDurationChange={(event) => setDuration(event.currentTarget.duration)}
        onLoadedMetadata={(event) => {
          if (source.resume_at_seconds > 0) event.currentTarget.currentTime = source.resume_at_seconds
        }}
        onError={() => setFailed(true)}
        className="size-full object-contain"
      >
        {source.subtitles.map((subtitle) => (
          <track key={subtitle.locale} kind="subtitles" src={subtitle.url} srcLang={subtitle.locale} label={subtitle.label} />
        ))}
      </video>

      <header className={cn('absolute inset-x-0 top-0 flex items-center gap-3 bg-gradient-to-b from-black/80 to-transparent p-3 transition-opacity sm:p-5', showControls ? 'opacity-100' : 'pointer-events-none opacity-0')}>
        <h2 className="min-w-0 flex-1 truncate font-semibold">{title}</h2>
        <button type="button" aria-label="Fechar" className={button} onClick={onClose}>
          <X className="size-6" />
        </button>
      </header>

      {failed && (
        <div role="alert" className="absolute inset-0 grid place-items-center bg-black/80 p-6 text-center">
          <p>Não foi possível reproduzir este vídeo. Verifique sua conexão e tente novamente.</p>
        </div>
      )}

      {ended && !failed && (
        <div className="absolute inset-0 grid place-items-center overflow-auto bg-black/85 p-6">
          <div className="w-full max-w-3xl">
            <p className="font-heading text-2xl font-bold">Vídeo concluído</p>
            <div className="mt-4 flex flex-wrap gap-3">
              {source.next && (
                <Link to={`/materiais/${source.next.slug}?abrir=1`} className="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 font-bold text-black transition hover:bg-white/85">
                  <SkipForward className="size-5" />
                  Próximo: {source.next.title}
                </Link>
              )}
              <button type="button" onClick={restart} className="inline-flex items-center gap-2 rounded-lg bg-white/15 px-5 py-2.5 font-bold transition hover:bg-white/25">
                <RotateCcw className="size-5" />
                Assistir de novo
              </button>
            </div>
            {related.length > 0 && (
              <>
                <p className="mt-8 mb-3 text-sm font-semibold text-white/70">Conteúdos relacionados</p>
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                  {related.slice(0, 4).map((item) => (
                    <li key={item.id}>
                      <Link to={`/materiais/${item.slug}`} className="block text-sm hover:underline">
                        <span className="line-clamp-2">{item.title}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              </>
            )}
          </div>
        </div>
      )}

      {!ended && (
        <div className={cn('absolute inset-x-0 bottom-0 grid gap-1 bg-gradient-to-t from-black/90 to-transparent px-3 pt-10 pb-3 transition-opacity sm:px-5', showControls ? 'opacity-100' : 'pointer-events-none opacity-0')}>
          <input
            type="range"
            aria-label="Posição do vídeo"
            min={0}
            max={duration || 0}
            step={1}
            value={Math.min(time, duration || 0)}
            onChange={seek}
            className="h-1.5 w-full cursor-pointer accent-[var(--brand)]"
          />
          <div className="flex flex-wrap items-center gap-1">
            <button type="button" aria-label={playing ? 'Pausar' : 'Reproduzir'} className={button} onClick={toggle}>
              {playing ? <Pause className="size-5 fill-current" /> : <Play className="size-5 fill-current" />}
            </button>
            <button type="button" aria-label={muted ? 'Ativar som' : 'Desativar som'} className={button} onClick={() => setMuted(!muted)}>
              {muted || volume === 0 ? <VolumeX className="size-5" /> : <Volume2 className="size-5" />}
            </button>
            <input
              type="range"
              aria-label="Volume"
              min={0}
              max={1}
              step={0.05}
              value={muted ? 0 : volume}
              onChange={(event) => {
                setVolume(Number(event.target.value))
                setMuted(false)
              }}
              className="hidden h-1 w-20 cursor-pointer accent-white sm:block"
            />
            <span className="ml-2 text-xs tabular-nums">{formatTime(time)} / {formatTime(duration)}</span>

            <div className="ml-auto flex flex-wrap items-center justify-end gap-1">
              {saveProgress && (
                <button
                  type="button"
                  aria-pressed={completed}
                  disabled={completed}
                  onClick={markCompleted}
                  className="inline-flex h-8 items-center gap-1.5 rounded px-2 text-xs transition hover:bg-white/15 disabled:opacity-70"
                >
                  <Check className="size-4" />
                  {completed ? 'Concluído' : 'Marcar como concluído'}
                </button>
              )}
              {source.next && (
                <Link to={`/materiais/${source.next.slug}?abrir=1`} aria-label={`Próximo conteúdo: ${source.next.title}`} className={button}>
                  <SkipForward className="size-5" />
                </Link>
              )}
              {source.subtitles.length > 0 && (
                <button type="button" aria-pressed={captions} aria-label="Legendas" onClick={() => setCaptions(!captions)} className={cn(button, captions && 'bg-white/25')}>
                  <Captions className="size-5" />
                </button>
              )}
              <select aria-label="Velocidade" className={select} value={speed} onChange={(event) => setSpeed(Number(event.target.value))}>
                {SPEEDS.map((value) => <option key={value} value={value}>{value}x</option>)}
              </select>
              {levels.length > 1 && (
                <select aria-label="Qualidade" className={select} value={quality} onChange={changeQuality}>
                  <option value={-1}>Automática</option>
                  {levels.map((level) => <option key={level.index} value={level.index}>{level.label}</option>)}
                </select>
              )}
              <button type="button" aria-label={fullscreen ? 'Sair da tela cheia' : 'Tela cheia'} className={button} onClick={toggleFullscreen}>
                {fullscreen ? <Minimize className="size-5" /> : <Maximize className="size-5" />}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
