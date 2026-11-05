import type { CapacitorConfig } from '@capacitor/cli'

/**
 * O app abre o site publicado: login, sessão e atualizações valem na hora, sem novo APK.
 * Defina o endereço em CAP_SERVER_URL antes de rodar "npx cap sync android".
 */
const url = process.env.CAP_SERVER_URL

const config: CapacitorConfig = {
  appId: 'br.com.olavo.tvplus',
  appName: 'Olavo TV+',
  webDir: 'dist',
  backgroundColor: '#0b0b0f',
  server: url ? { url, cleartext: url.startsWith('http://') } : undefined,
  plugins: {
    PushNotifications: { presentationOptions: ['badge', 'sound', 'alert'] },
  },
}

export default config
