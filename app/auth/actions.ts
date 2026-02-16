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

  const { data: profile } = await supabase.from("profiles").select("role").eq("id", user.id).single();
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

    if (profileError) return { error: profileError.message };
  }

  if (!data.session) {
    return {
      success: "Signup successful. Please check your email and confirm your account before logging in.",
    };
  }

  if (role === "instructor") redirect("/instructor/dashboard");
  redirect("/student/dashboard");
}
