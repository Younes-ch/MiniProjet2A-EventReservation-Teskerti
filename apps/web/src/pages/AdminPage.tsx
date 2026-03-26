const metrics = [
  { label: 'Total events', value: '142' },
  { label: 'Total reservations', value: '3,892' },
  { label: 'Upcoming events', value: '24' },
]

export function AdminPage() {
  return (
    <section className="section-block">
      <p className="eyebrow">Admin shell</p>
      <h1>Dashboard overview</h1>
      <p className="section-copy">
        Phase 0 provides static layout blocks that will bind to analytics and
        CRUD APIs in upcoming phases.
      </p>

      <div className="metric-grid">
        {metrics.map((metric) => (
          <article key={metric.label} className="metric-card">
            <p>{metric.label}</p>
            <h2>{metric.value}</h2>
          </article>
        ))}
      </div>

      <article className="table-shell" aria-label="Recent events shell">
        <h2>Recent events</h2>
        <div className="table-row table-header">
          <span>Event</span>
          <span>Date and time</span>
          <span>Status</span>
        </div>
        <div className="table-row">
          <span>Nexus Tech Summit</span>
          <span>Oct 24, 2024 - 09:00</span>
          <span>Active</span>
        </div>
        <div className="table-row">
          <span>Artisanal Fashion Week</span>
          <span>Nov 02, 2024 - 19:30</span>
          <span>Drafting</span>
        </div>
      </article>
    </section>
  )
}