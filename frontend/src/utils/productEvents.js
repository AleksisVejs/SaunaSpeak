import api from '../api'

// Mirror a few high-value product milestones to our own database. Umami stays
// useful for browsing behavior; these sparse events remain visible when it is
// blocked and can be joined to checkout/subscription outcomes.
export function recordProductEvent(event) {
  window.umami?.track(event)
  return api.post('/product-events', { event }).catch(() => null)
}
