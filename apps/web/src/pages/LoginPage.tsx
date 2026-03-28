const trustSignals = [
  {
    title: "Passwordless entry",
    copy: "Sign in with FaceID or TouchID for seamless access.",
    toneClass: "signal-tone-indigo",
    iconLabel: "Lock",
  },
  {
    title: "End-to-end security",
    copy: "Your data is protected by enterprise-grade encryption.",
    toneClass: "signal-tone-cyan",
    iconLabel: "Shield",
  },
];

export function LoginPage() {
  return (
    <section className="auth-shell auth-shell-login">
      <div className="auth-intro">
        <p className="eyebrow">Identity access</p>
        <h1>
          Design your
          <br />
          next masterpiece.
        </h1>
        <p className="hero-copy">
          Access the world&apos;s most sophisticated event management ecosystem.
          Securely enter using industry-leading biometric authentication.
        </p>

        <ul className="auth-signal-list" aria-label="Security highlights">
          {trustSignals.map((signal) => (
            <li key={signal.title} className="auth-signal-item">
              <span className={`auth-signal-icon ${signal.toneClass}`}>
                <svg viewBox="0 0 24 24" aria-label={signal.iconLabel}>
                  <path d="M12 2l7 3v6c0 5-3.6 9.6-7 11-3.4-1.4-7-6-7-11V5l7-3z" />
                  <rect x="9" y="10" width="6" height="6" rx="1" />
                </svg>
              </span>
              <div>
                <h3>{signal.title}</h3>
                <p>{signal.copy}</p>
              </div>
            </li>
          ))}
        </ul>

        <footer className="auth-meta-links">
          <a href="#">Privacy Policy</a>
          <a href="#">Terms of Service</a>
        </footer>
      </div>

      <article className="auth-card auth-card-login">
        <h2>Welcome Back</h2>
        <p className="auth-card-sub">Choose your preferred way to continue</p>

        <button type="button" className="button-primary wide passkey-button">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="8" />
            <path d="M9.5 12.2c.7-1.2 2.4-1.6 3.6-.9 1.2.7 1.6 2.4.9 3.6" />
            <path d="M10.2 9.7a4.6 4.6 0 0 1 5 7.6" />
          </svg>
          Continue with Passkey
        </button>

        <div className="separator">or fallback to email</div>

        <form className="auth-form" onSubmit={(event) => event.preventDefault()}>
          <label htmlFor="email">Email address</label>
          <input id="email" type="email" placeholder="alex@example.com" />

          <div className="auth-label-row">
            <label htmlFor="password">Password</label>
            <a href="#">Forgot password?</a>
          </div>
          <input id="password" type="password" placeholder="........" />

          <button type="submit" className="button-secondary wide">
            Sign in with password
          </button>
        </form>

        <p className="auth-card-footer">
          Don&apos;t have an account? <a href="#">Create an account</a>
        </p>
      </article>
    </section>
  );
}
