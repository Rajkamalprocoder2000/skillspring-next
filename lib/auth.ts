import { redirect } from "next/navigation";
import { createServerSupabaseClient } from "@/lib/supabase/server";

export type AppRole = "admin" | "instructor" | "student";

export type Profile = {
  id: string;
  full_name: string | null;
  role: AppRole;
};

export async function getCurrentProfile(supabaseArg?: Awaited<ReturnType<typeof createServerSupabaseClient>>): Promise<Profile | null> {
  const supabase = supabaseArg ?? (await createServerSupabaseClient());
  const {
    data: { user },
  } = await supabase.auth.getUser();

  if (!user) return null;

  const { data } = await supabase
    .from("profiles")
    .select("id, full_name, role")
    .eq("id", user.id)
    .single();

  return (data as Profile | null) ?? null;
}

export async function requireProfile(roles?: AppRole[]) {
  const supabase = await createServerSupabaseClient();
  const profile = await getCurrentProfile(supabase);

  if (!profile) {
    redirect("/auth/login");
  }

  if (roles && !roles.includes(profile.role)) {
    redirect("/");
  }

  return { supabase, profile };
}
