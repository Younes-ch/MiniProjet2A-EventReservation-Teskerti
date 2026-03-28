const metrics = [
  { label: "Total events", value: "142", trend: "+12%", tone: "metric-tone-1" },
  {
    label: "Total reservations",
    value: "3,892",
    trend: "+8%",
    tone: "metric-tone-2",
  },
  {
    label: "Upcoming events",
    value: "24",
    trend: "Active",
    tone: "metric-tone-3",
  },
];

const recentEvents = [
  {
    title: "Nexus Tech Summit 2024",
    venue: "Main Hall, Convention Center",
    date: "Oct 24, 2024",
    time: "09:00 AM",
    status: "Active",
  },
  {
    title: "Artisanal Fashion Week",
    venue: "Downtown Studios",
    date: "Nov 02, 2024",
    time: "07:30 PM",
    status: "Drafting",
  },
  {
    title: "Midnight Gala Night",
    venue: "The Grand Ballroom",
    date: "Oct 30, 2024",
    time: "10:00 PM",
    status: "Sold Out",
  },
];

const insights = [
  {
    label: "Most popular",
    detail: "Tech Summit Reservations",
    note: "98% capacity reached",
  },
  {
    label: "Revenue today",
    detail: "$12,450.00",
    note: "+2.4% from yesterday",
  },
];

export function AdminPage() {
  return (
    <section className="section-block admin-shell">
      <header className="admin-shell-header">
        <div>
          <p className="eyebrow">Admin shell</p>
          <h1>Dashboard Overview</h1>
          <p className="section-copy">
            Welcome back, Alex. Here&apos;s what&apos;s happening with your events
            today.
          </p>
        </div>

        <div className="admin-search-row">
          <input
            type="search"
            className="admin-search-input"
            placeholder="Search analytics..."
            aria-label="Search analytics"
          />
          <button type="button" className="admin-alert-button">
            Alerts
          </button>
        </div>
      </header>

      <div className="admin-kpi-grid">
        {metrics.map((metric) => (
          <article key={metric.label} className="metric-card admin-metric-card">
            <span className={`admin-metric-dot ${metric.tone}`} aria-hidden="true" />
            <span className="admin-metric-trend">{metric.trend}</span>
            <p>{metric.label}</p>
            <h2>{metric.value}</h2>
          </article>
        ))}
      </div>

      <div className="admin-main-grid">
        <article className="table-shell admin-events" aria-label="Recent events shell">
          <header className="admin-events-head">
            <h2>Recent Events</h2>
            <a href="#">View all</a>
          </header>

          <div className="table-row table-header admin-events-header">
            <span>Event details</span>
            <span>Date and time</span>
            <span>Status</span>
          </div>

          {recentEvents.map((event) => (
            <div className="table-row admin-events-row" key={event.title}>
              <span>
                <strong>{event.title}</strong>
                <small>{event.venue}</small>
              </span>
              <span>
                <strong>{event.date}</strong>
                <small>{event.time}</small>
              </span>
              <span className="admin-status-chip">{event.status}</span>
            </div>
          ))}
        </article>

        <aside className="admin-side-stack">
          <article className="admin-insight-card">
            <h2>Quick Insights</h2>
            <ul>
              {insights.map((insight) => (
                <li key={insight.label}>
                  <p>{insight.label}</p>
                  <strong>{insight.detail}</strong>
                  <span>{insight.note}</span>
                </li>
              ))}
            </ul>
            <button type="button" className="button-secondary wide">
              Export Reports
            </button>
          </article>

          <article className="admin-plan-card">
            <h2>Need a custom plan?</h2>
            <p>
              Upgrade to our enterprise architecture for unlimited events and
              advanced AI analytics.
            </p>
            <button type="button" className="button-primary">
              Explore Plans
            </button>
          </article>
        </aside>
      </div>
    </section>
  );
}
