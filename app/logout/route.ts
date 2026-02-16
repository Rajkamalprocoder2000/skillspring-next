import { NextResponse } from "next/server";
import { createServerSupabaseClient } from "@/lib/supabase/server";

export async function POST(request: Request) {
  const supabase = await createServerSupabaseClient();
  await supabase.auth.signOut();
  return NextResponse.redirect(new URL("/", request.url));
}

export async function GET(request: Request) {
  // Avoid accidental signout from link prefetchers.
  return NextResponse.redirect(new URL("/", request.url));
}
