"use server";

import { redirect } from "next/navigation";
import { createServerSupabaseClient } from "@/lib/supabase/server";

export type AuthState = {
  error?: string;
  success?: string;
};

function normalizeRole(value: string): "student" | "instructor" {
  return value === "instructor" ? "instructor" : "student";
}

function isProfilesTableMissing(message: string): boolean {
  const lower = message.toLowerCase();
  return lower.includes("public.profiles") || lower.includes("relation \"profiles\" does not exist");
}

const setupHelp =
  "Database setup is incomplete. Run supabase/schema.sql in Supabase SQL Editor, then retry.";

export async function loginAction(_: AuthState, formData: FormData): Promise<AuthState> {
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  if (!email || !password) {
    return { error: "Email and password are required." };
  }

  const supabase = await createServerSupabaseClient();
  const { error } = await supabase.auth.signInWithPassword({ email, password });
  if (error) {
    if (error.message.toLowerCase().includes("invalid login credentials")) {
      return {
        error: "Invalid credentials, or your email is not confirmed yet. Please verify your inbox and try again.",
      };
    }
    return { error: error.message };
  }

  const {
    data: { user },
  } = await supabase.auth.getUser();

  if (!user) {
    return { error: "Unable to load your account." };
  }

  let { data: profile, error: profileError } = await supabase
    .from("profiles")
    .select("role")
    .eq("id", user.id)
    .maybeSingle();

  if (profileError && isProfilesTableMissing(profileError.message)) {
    return { error: setupHelp };
  }

  if (!profile) {
    const rawRole = user.user_metadata?.role;
    const fallbackRole = rawRole === "admin" || rawRole === "instructor" ? rawRole : "student";
    const { data: inserted, error: insertError } = await supabase
      .from("profiles")
      .insert({
        id: user.id,
        email: user.email ?? "",
        full_name: (user.user_metadata?.full_name as string | undefined) ?? null,
        role: fallbackRole,
      })
      .select("role")
      .single();

    if (insertError) {
      if (isProfilesTableMissing(insertError.message)) {
        return { error: setupHelp };
      }
      return { error: insertError.message };
    }

    profile = inserted;
    profileError = null;
  }

  const role = profile?.role as string | undefined;

  if (role === "admin") redirect("/admin/dashboard");
  if (role === "instructor") redirect("/instructor/dashboard");
  redirect("/student/dashboard");
}

export async function signupAction(_: AuthState, formData: FormData): Promise<AuthState> {
  const fullName = String(formData.get("full_name") ?? "").trim();
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");
  const role = normalizeRole(String(formData.get("role") ?? "student"));

  if (!fullName || !email || !password) {
    return { error: "All fields are required." };
  }
  if (password.length < 6) {
    return { error: "Password must be at least 6 characters." };
  }

  const supabase = await createServerSupabaseClient();
  const { data, error } = await supabase.auth.signUp({
    email,
    password,
    options: {
      data: {
        full_name: fullName,
        role,
      },
    },
  });

  if (error) return { error: error.message };

  if (data.user?.id) {
    const { error: profileError } = await supabase.from("profiles").upsert({
      id: data.user.id,
      email,
      full_name: fullName,
      role,
    });

    if (profileError) {
      if (isProfilesTableMissing(profileError.message)) {
        return { error: setupHelp };
      }
      return { error: profileError.message };
    }
  }

  if (!data.session) {
    return {
      success: "Signup successful. Please check your email and confirm your account before logging in.",
    };
  }

  if (role === "instructor") redirect("/instructor/dashboard");
  redirect("/student/dashboard");
}
