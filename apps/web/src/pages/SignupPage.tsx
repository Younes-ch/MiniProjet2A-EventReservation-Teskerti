import { type FormEvent, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { signupWithPassword } from "../lib/authClient";

const mapSignupError = (error: unknown): string => {
  if (!(error instanceof Error)) {
    return "Unable to create account right now.";
  }

  if (error.message === "signup_payload_invalid") {
    return "Please provide a valid name, email, and password.";
  }

  if (error.message === "signup_password_too_short") {
    return "Password must be at least 8 characters.";
  }

  if (error.message === "signup_email_already_exists") {
    return "An account with this email already exists.";
  }

  return "Unable to create account right now.";
};

export function SignupPage() {
  const navigate = useNavigate();
  const [displayName, setDisplayName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [isSubmitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const normalizedDisplayName = displayName.trim();
    const normalizedEmail = email.trim().toLowerCase();

    if (password !== confirmPassword) {
      setErrorMessage("Password confirmation does not match.");
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);

    try {
      await signupWithPassword({
        email: normalizedEmail,
        display_name: normalizedDisplayName,
        password,
      });

      navigate("/login", {
        replace: true,
        state: {
          noticeMessage: "Account created. You can now sign in.",
          prefillEmail: normalizedEmail,
        },
      });
    } catch (error) {
      setErrorMessage(mapSignupError(error));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section className="auth-shell auth-shell-login">
      <article className="auth-card auth-card-login">
        <h2>Create account</h2>
        <p className="auth-card-sub">Set up your Tiskerti user profile</p>

        <form className="auth-form" onSubmit={handleSubmit}>
          <label htmlFor="signup-name">Display name</label>
          <input
            id="signup-name"
            type="text"
            required
            value={displayName}
            onChange={(event) => setDisplayName(event.target.value)}
          />

          <label htmlFor="signup-email">Email address</label>
          <input
            id="signup-email"
            type="email"
            required
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />

          <label htmlFor="signup-password">Password</label>
          <input
            id="signup-password"
            type="password"
            required
            minLength={8}
            autoComplete="new-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />

          <label htmlFor="signup-password-confirm">Confirm password</label>
          <input
            id="signup-password-confirm"
            type="password"
            required
            minLength={8}
            autoComplete="new-password"
            value={confirmPassword}
            onChange={(event) => setConfirmPassword(event.target.value)}
          />

          <button
            type="submit"
            className="button-primary wide"
            disabled={isSubmitting}
          >
            {isSubmitting ? "Creating account..." : "Create account"}
          </button>
        </form>

        {errorMessage ? (
          <p className="auth-form-status auth-form-status-error" role="alert">
            {errorMessage}
          </p>
        ) : null}

        <p className="auth-card-footer">
          Already have an account? <Link to="/login">Sign in</Link>
        </p>
      </article>
    </section>
  );
}
