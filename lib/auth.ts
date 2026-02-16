import { redirect } from "next/navigation";
import { createServerSupabaseClient } from "@/lib/supabase/server";
import type { User } from "@supabase/supabase-js";

export type AppRole = "admin" | "instructor" | "student";

export type Profile = {
  id: string;
  email?: string | null;
  full_name: string | null;
  role: AppRole;
  is_active: boolean;
};

async function getOrCreateProfile(
  supabase: Awaited<ReturnType<typeof createServerSupabaseClient>>,
  user: User,
): Promise<Profile | null> {

  const { data, error } = await supabase
    .from("profiles")
    .select("id, email, full_name, role, is_active")
    .eq("id", user.id)
    .maybeSingle();

  if (error) {
    return null;
  }

  if (data) {
    return data as Profile;
  }

  const rawRole = user.user_metadata?.role;
  const role: AppRole = rawRole === "admin" || rawRole === "instructor" ? rawRole : "student";

  const { data: inserted, error: insertError } = await supabase
    .from("profiles")
    .insert({
      id: user.id,
      email: user.email ?? "",
      full_name: (user.user_metadata?.full_name as string | undefined) ?? null,
      role,
      is_active: true,
    })
    .select("id, email, full_name, role, is_active")
    .single();

  if (insertError) {
    return null;
  }

  return (inserted as Profile | null) ?? null;
}

export async function getCurrentProfile(
  supabaseArg?: Awaited<ReturnType<typeof createServerSupabaseClient>>,
  userArg?: User,
): Promise<Profile | null> {
  const supabase = supabaseArg ?? (await createServerSupabaseClient());
  let user = userArg;

  if (!user) {
    const {
      data: { user: authUser },
    } = await supabase.auth.getUser();
    user = authUser ?? undefined;
  }

  if (!user) return null;
  return getOrCreateProfile(supabase, user);
}

export async function requireProfile(roles?: AppRole[]) {
  const supabase = await createServerSupabaseClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();

  if (!user) {
    redirect("/auth/login");
  }

  const profile = await getCurrentProfile(supabase, user);

  if (!profile) {
    redirect("/auth/login");
  }

  if (roles && !roles.includes(profile.role)) {
    redirect("/");
  }

  if (!profile.is_active) {
    await supabase.auth.signOut();
    redirect("/auth/login");
  }

  return { supabase, profile };
}
