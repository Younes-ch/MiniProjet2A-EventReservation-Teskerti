const featuredEvents = [
  {
    title: 'Midnight Resonance 2.0',
    venue: 'The Warehouse District',
    price: '$45.00',
    badge: '82 seats available',
  },
  {
    title: 'Ephemeral Visions Gallery',
    venue: 'Skyline Atrium',
    price: '$120.00',
    badge: '12 seats left',
  },
  {
    title: 'Future Loop: AI 2024',
    venue: 'Innovation Hub',
    price: '$299.00',
    badge: 'Sold out soon',
  },
]

export function HomePage() {
  return (
    <>
      <section className="hero-section">
        <div>
          <p className="eyebrow">Public event discovery shell</p>
          <h1>Experience the best events</h1>
          <p className="hero-copy">
            This Phase 0 page is the base shell for upcoming reservation flows,
            detail views, and seat selection.
          </p>
          <div className="hero-actions">
            <button type="button" className="button-primary">
              Explore now
            </button>
            <button type="button" className="button-secondary">
              How it works
            </button>
          </div>
        </div>
        <aside className="hero-card" aria-label="Trending event">
          <p className="eyebrow">Trending now</p>
          <h2>Neon Nights 2024</h2>
          <p>Event showcase tile placeholder for future API data.</p>
        </aside>
      </section>

      <section className="section-block">
        <h2>Upcoming experiences</h2>
        <p className="section-copy">
          Cards remain static in Phase 0 and will switch to backend data in Phase
          1.
        </p>
        <div className="event-grid">
          {featuredEvents.map((event) => (
            <article key={event.title} className="event-card">
              <p className="badge">{event.badge}</p>
              <h3>{event.title}</h3>
              <p>{event.venue}</p>
              <p className="price">{event.price}</p>
            </article>
          ))}
        </div>
      </section>
    </>
  )
}