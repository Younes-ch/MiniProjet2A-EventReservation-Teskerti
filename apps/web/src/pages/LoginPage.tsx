export function LoginPage() {
  return (
    <section className="auth-shell">
      <div>
        <p className="eyebrow">Authentication shell</p>
        <h1>Welcome back</h1>
        <p className="hero-copy">
          Password login is the baseline for Phase 1. Passkey registration and
          sign-in will layer in during Phase 2.
        </p>
      </div>

      <article className="auth-card">
        <h2>Choose how to continue</h2>
        <button type="button" className="button-primary wide">
          Continue with passkey
        </button>
        <div className="separator">or fallback to email</div>
        <form className="auth-form">
          <label htmlFor="email">Email address</label>
          <input id="email" type="email" placeholder="alex@example.com" />

          <label htmlFor="password">Password</label>
          <input id="password" type="password" placeholder="........" />

          <button type="button" className="button-secondary wide">
            Sign in with password
          </button>
        </form>
      </article>
    </section>
  )
}