// Per-page head tags for pages whose SEO text depends on fetched data
// (lesson previews). The router's afterEach hook writes route-level
// defaults on navigation; pages call this after loading to overwrite them
// with the real title/description.
export function setPageHead({ title, description }) {
  if (title) document.title = title

  if (description) {
    let meta = document.head.querySelector('meta[name="description"]')
    if (!meta) {
      meta = document.createElement('meta')
      meta.name = 'description'
      document.head.appendChild(meta)
    }
    meta.content = description
  }
}
