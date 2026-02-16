"use client";

import Link from "next/link";
import { useActionState } from "react";
import { signupAction, type AuthState } from "@/app/auth/actions";

const initialState: AuthState = {};

export default function SignupPage() {
  const [state, formAction, pending] = useActionState(signupAction, initialState);

  return (
    <section className="card" style={{ maxWidth: 620, margin: "30px auto" }}>
      <h1>Create Account</h1>
      <p className="muted">Join as student or instructor.</p>
      {state.error && <p style={{ color: "#b91c1c" }}>{state.error}</p>}
      {state.success && <p style={{ color: "#166534" }}>{state.success}</p>}
      <form action={formAction}>
        <label>Full Name</label>
        <input name="full_name" required />
        <label>Email</label>
        <input name="email" type="email" required />
        <label>Password</label>
        <input name="password" type="password" minLength={6} required />
        <label>Role</label>
        <select name="role" defaultValue="student">
          <option value="student">Student</option>
          <option value="instructor">Instructor</option>
        </select>
        <button className="btn" disabled={pending} type="submit">
          {pending ? "Creating..." : "Sign up"}
        </button>
      </form>
      <p>
        Already have an account? <Link href="/auth/login">Login</Link>
      </p>
    </section>
  );
}
