import { Link } from 'react-router'
import { usePublicConfig } from '../config'

const PAGES = {
  terms: { title: 'Termos de uso', key: 'legal.terms' },
  privacy: { title: 'Política de privacidade', key: 'legal.privacy' },
}

function LegalPage({ page }: { page: keyof typeof PAGES }) {
  const config = usePublicConfig()
  const { title, key } = PAGES[page]
  const text = config.data?.[key] as string | null | undefined

  return (
    <main className="dark min-h-dvh bg-background px-4 py-12 text-foreground">
      <div className="mx-auto max-w-2xl">
        <h1 className="font-heading text-3xl font-bold tracking-tight">{title}</h1>
        {config.isSuccess && !text && <p className="mt-6 text-muted-foreground">Este texto ainda não foi publicado.</p>}
        {text && <p className="mt-6 whitespace-pre-line leading-relaxed text-foreground/85">{text}</p>}
        <Link to="/" className="mt-8 inline-block text-brand hover:underline">Voltar ao início</Link>
      </div>
    </main>
  )
}

export function TermsPage() {
  return <LegalPage page="terms" />
}

export function PrivacyPage() {
  return <LegalPage page="privacy" />
}
