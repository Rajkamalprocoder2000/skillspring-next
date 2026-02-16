"use client";

import Link from "next/link";
import { useActionState } from "react";
import { loginAction, type AuthState } from "@/app/auth/actions";

const initialState: AuthState = {};

export default function LoginPage() {
  const [state, formAction, pending] = useActionState(loginAction, initialState);

  return (
    <section className="card" style={{ maxWidth: 520, margin: "30px auto" }}>
      <h1>Login</h1>
      <p className="muted">Continue your learning journey.</p>
      {state.error && <p style={{ color: "#b91c1c" }}>{state.error}</p>}
      <form action={formAction}>
        <label>Email</label>
        <input name="email" type="email" required />
        <label>Password</label>
        <input name="password" type="password" required />
        <button className="btn" disabled={pending} type="submit">
          {pending ? "Signing in..." : "Login"}
        </button>
      </form>
      <p>
        New here? <Link href="/auth/signup">Create account</Link>
      </p>
    </section>
  );
}
