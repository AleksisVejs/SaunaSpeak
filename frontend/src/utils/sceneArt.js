// Art for the Tilanteet scenarios, one character + one backdrop per scenario
// id (matching backend app/Support/Scenarios.php). Lives in /scenes, which is
// excluded from the PWA precache (see vite.config.js) - the art loads on
// demand and is then runtime-cached, so installing the app stays light.
export const SCENE_ART = {
  kauppa: { character: '/scenes/Kauppa.png', background: '/scenes/shop.jpg' },
  kahvila: { character: '/scenes/Kahvila.png', background: '/scenes/cafe.jpg' },
  bussi: { character: '/scenes/Bussi.png', background: '/scenes/bus.jpg' },
  apteekki: { character: '/scenes/Apteekki.png', background: '/scenes/pharmacy.jpg' },
  ravintola: { character: '/scenes/Ravintola.png', background: '/scenes/restaurant.jpg' },
  naapuri: { character: '/scenes/Naapuri.png', background: '/scenes/suburb.jpg' },
  tori: { character: '/scenes/Tori.png', background: '/scenes/market.jpg' },
  saunailta: { character: '/scenes/Saunailta.png', background: '/scenes/sauna.jpg' }
}
