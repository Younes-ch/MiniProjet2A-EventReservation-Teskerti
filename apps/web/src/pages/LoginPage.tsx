import { type FormEvent, useEffect, useRef, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import {
  fetchPasskeyOptions,
  loginWithPassword,
  type AuthTokenResponse,
  type PasskeyOptionsResponse,
  type PasswordLoginResponse,
  verifyPasskeyLogin,
} from "../lib/authClient";
import { saveAuthSession } from "../lib/authStorage";
import {
  base64UrlToBuffer,
  preparePasskeyClientData,
} from "../lib/webauthnUtils";

const trustSignals = [
  {
    title: "Passwordless entry",
    copy: "Sign in with FaceID or TouchID for seamless access.",
    toneClass: "signal-tone-indigo",
  },
  {
    title: "End-to-end security",
    copy: "Your data is protected by enterprise-grade encryption.",
    toneClass: "signal-tone-cyan",
  },
];

const isPasskeyStepResponse = (
  response: PasswordLoginResponse,
): response is Extract<PasswordLoginResponse, { requires_passkey: true }> =>
  "requires_passkey" in response && response.requires_passkey === true;

const persistAuthSession = (response: AuthTokenResponse) => {
  saveAuthSession({
    accessToken: response.access_token,
    refreshToken: response.refresh_token,
    tokenType: response.token_type,
    expiresIn: response.expires_in,
    user: response.user,
  });
};

export function LoginPage() {
  const location = useLocation();
  const locationState = location.state as {
    noticeMessage?: string;
    prefillEmail?: string;
  } | null;
  const [email, setEmail] = useState("alex@example.com");
  const [password, setPassword] = useState("Passw0rd!2026");
  const [isSubmitting, setSubmitting] = useState(false);
  const [isPasskeySubmitting, setPasskeySubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const redirectTimeoutRef = useRef<number | null>(null);
  const navigate = useNavigate();

  useEffect(() => {
    if (!locationState) {
      return;
    }

    if (locationState.prefillEmail && locationState.prefillEmail.length > 0) {
      setEmail(locationState.prefillEmail);
    }

    if (locationState.noticeMessage && locationState.noticeMessage.length > 0) {
      setSuccessMessage(locationState.noticeMessage);
    }
  }, [locationState]);

  const completePasskeySignIn = async (
    normalizedEmail: string,
    options: PasskeyOptionsResponse,
  ) => {
    const firstAllowedCredential = options.allow_credentials[0]?.id ?? "";
    if (firstAllowedCredential.length === 0) {
      throw new Error("passkey_not_registered");
    }

    const allowCredentialsList = options.allow_credentials.map((cred) => ({
      id: base64UrlToBuffer(cred.id),
      type: cred.type as "public-key",
    }));

    const credential = await navigator.credentials.get({
      publicKey: {
        challenge: base64UrlToBuffer(options.challenge),
        rpId: options.rp_id,
        allowCredentials: allowCredentialsList,
        userVerification: options.user_verification as UserVerificationRequirement,
        timeout: options.timeout,
      },
    }) as PublicKeyCredential;

    if (!credential) {
      throw new Error("Passkey login cancelled or failed.");
    }

    const response = await verifyPasskeyLogin({
      email: normalizedEmail,
      challenge: options.challenge,
      credential_id: credential.id,
      client_data: preparePasskeyClientData("webauthn.get", options.challenge),
    });

    persistAuthSession(response);
  };

  useEffect(() => {
    return () => {
      if (redirectTimeoutRef.current !== null) {
        window.clearTimeout(redirectTimeoutRef.current);
      }
    };
  }, []);

  const mapAuthErrorMessage = (error: unknown): string => {
    if (!(error instanceof Error)) {
      return "Unable to sign in right now. Please try again.";
    }

    if ("invalid_credentials" === error.message) {
      return "Invalid email or password.";
    }

    if ("email_and_password_required" === error.message) {
      return "Email and password are required.";
    }

    if ("invalid_json_payload" === error.message) {
      return "Unexpected request format while signing in.";
    }

    if ("email_required" === error.message) {
      return "Email is required to sign in with passkey.";
    }

    if ("passkey_not_registered" === error.message) {
      return "No passkey is registered for this account yet.";
    }

    if ("passkey_required_but_not_registered" === error.message) {
      return "This account requires passkey verification, but no passkey is registered.";
    }

    if ("passkey_verification_required" === error.message) {
      return "Passkey verification is required before you can continue.";
    }

    if ("passkey_origin_invalid" === error.message) {
      return "This passkey request origin is not allowed.";
    }

    if (
      "passkey_challenge_invalid" === error.message ||
      "passkey_credential_invalid" === error.message ||
      "passkey_payload_invalid" === error.message
    ) {
      return "Passkey verification failed. Try again.";
    }

    return "Auth service is unavailable. Check your API and try again.";
  };

  const handlePasskeySubmit = async () => {
    setErrorMessage(null);
    setSuccessMessage(null);
    setPasskeySubmitting(true);

    try {
      const normalizedEmail = email.trim().toLowerCase();
      if (!normalizedEmail) {
        setErrorMessage("Please enter your registered email below to sign in with your passkey.");
        setPasskeySubmitting(false);
        return;
      }

      const options = await fetchPasskeyOptions(normalizedEmail);
      await completePasskeySignIn(normalizedEmail, options);

      setSuccessMessage("Signed in with passkey. Redirecting...");
      redirectTimeoutRef.current = window.setTimeout(() => {
        navigate("/");
      }, 700);
    } catch (error) {
      setErrorMessage(mapAuthErrorMessage(error));
    } finally {
      setPasskeySubmitting(false);
    }
  };

  const handlePasswordSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setErrorMessage(null);
    setSuccessMessage(null);
    setSubmitting(true);

    try {
      const normalizedEmail = email.trim().toLowerCase();

      const response = await loginWithPassword({
        email: normalizedEmail,
        password,
      });

      if (isPasskeyStepResponse(response)) {
        setSuccessMessage(
          "Password accepted. Completing passkey verification...",
        );
        await completePasskeySignIn(normalizedEmail, response.passkey_options);

        setSuccessMessage(
          "Signed in with password + passkey. Redirecting...",
        );
        redirectTimeoutRef.current = window.setTimeout(() => {
          navigate("/");
        }, 700);

        return;
      }

      persistAuthSession(response);

      setSuccessMessage("Signed in successfully. Redirecting...");
      redirectTimeoutRef.current = window.setTimeout(() => {
        navigate("/");
      }, 700);
    } catch (error) {
      setErrorMessage(mapAuthErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  };

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
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
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
          <Link to="/">Privacy Policy</Link>
          <Link to="/">Terms of Service</Link>
        </footer>
      </div>

      <article className="auth-card auth-card-login">
        <h2>Welcome Back</h2>
        <p className="auth-card-sub">Choose your preferred way to continue</p>
        <p className="auth-demo-credentials">
          Demo account: alex@example.com / Passw0rd!2026
        </p>

        <button
          type="button"
          className="button-primary wide passkey-button"
          onClick={() => void handlePasskeySubmit()}
          disabled={isSubmitting || isPasskeySubmitting}
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="8" />
            <path d="M9.5 12.2c.7-1.2 2.4-1.6 3.6-.9 1.2.7 1.6 2.4.9 3.6" />
            <path d="M10.2 9.7a4.6 4.6 0 0 1 5 7.6" />
          </svg>
          {isPasskeySubmitting
            ? "Verifying passkey..."
            : "Sign in with passkey"}
        </button>

        <div className="separator">or fallback to email</div>

        <form className="auth-form" onSubmit={handlePasswordSubmit}>
          <label htmlFor="email">Email address</label>
          <input
            id="email"
            type="email"
            placeholder="alex@example.com"
            autoComplete="email"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />

          <div className="auth-label-row">
            <label htmlFor="password">Password</label>
            <Link to="/forgot-password">Forgot password?</Link>
          </div>
          <input
            id="password"
            type="password"
            placeholder="........"
            autoComplete="current-password"
            required
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />

          <button
            type="submit"
            className="button-secondary wide"
            disabled={isSubmitting}
          >
            {isSubmitting ? "Signing in..." : "Sign in with password"}
          </button>
        </form>

        {errorMessage ? (
          <p className="auth-form-status auth-form-status-error" role="alert">
            {errorMessage}
          </p>
        ) : null}

        {successMessage ? (
          <p
            className="auth-form-status auth-form-status-success"
            role="status"
          >
            {successMessage}
          </p>
        ) : null}

        <p className="auth-card-footer">
          Don&apos;t have an account?{" "}
          <Link to="/signup">Create an account</Link>
        </p>
      </article>
    </section>
  );
}
