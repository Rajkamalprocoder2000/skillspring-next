import { NextResponse, type NextRequest } from "next/server";
import { updateSession } from "@/lib/supabase/middleware";

const protectedPrefixes = ["/student", "/instructor", "/admin"];

export function proxy(request: NextRequest) {
  const response = updateSession(request);
  const pathname = request.nextUrl.pathname;

  const isProtected = protectedPrefixes.some((prefix) => pathname.startsWith(prefix));
  if (!isProtected) return response;

  const hasSession =
    request.cookies.getAll().some((cookie) => cookie.name.includes("sb-") && cookie.name.includes("auth-token"));

  if (!hasSession) {
    const loginUrl = request.nextUrl.clone();
    loginUrl.pathname = "/auth/login";
    return NextResponse.redirect(loginUrl);
  }

  return response;
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};

