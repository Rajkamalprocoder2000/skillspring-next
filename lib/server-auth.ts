import { redirect } from "next/navigation";
import { createServerSupabaseClient } from "@/lib/supabase/server";
import { getCurrentProfile, type AppRole } from "@/lib/auth";

export async function requireActionProfile(roles?: AppRole[]) {
  const supabase = await createServerSupabaseClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();

  if (!user) {
    redirect("/auth/login");
  }

  const profile = await getCurrentProfile(supabase);
  if (!profile) {
    redirect("/auth/login");
  }

  if (!profile.is_active) {
    await supabase.auth.signOut();
    redirect("/auth/login");
  }

  if (roles && !roles.includes(profile.role)) {
    redirect("/");
  }

  return { supabase, user, profile };
}

