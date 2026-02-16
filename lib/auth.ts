import { redirect } from "next/navigation";
import { createServerSupabaseClient } from "@/lib/supabase/server";

export type AppRole = "admin" | "instructor" | "student";

export type Profile = {
  id: string;
  email?: string | null;
  full_name: string | null;
  role: AppRole;
};

export async function getCurrentProfile(supabaseArg?: Awaited<ReturnType<typeof createServerSupabaseClient>>): Promise<Profile | null> {
  const supabase = supabaseArg ?? (await createServerSupabaseClient());
  const {
    data: { user },
  } = await supabase.auth.getUser();

  if (!user) return null;

  const { data, error } = await supabase
    .from("profiles")
    .select("id, email, full_name, role")
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
    })
    .select("id, email, full_name, role")
    .single();

  if (insertError) {
    return null;
  }

  return (inserted as Profile | null) ?? null;
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
