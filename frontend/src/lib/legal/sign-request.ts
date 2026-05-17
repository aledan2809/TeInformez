import { createHmac, createHash, randomBytes } from "node:crypto";

export const APP_SLUG = "teinformez";

export interface SignedHeaders {
  "x-app-slug": string;
  "x-app-timestamp": string;
  "x-app-nonce": string;
  "x-app-signature": string;
  "x-user-id": string;
}

interface SignArgs {
  globalUserId: string;
  bodyText: string;
}

export function tiGlobalUserId(userId: number | string): string {
  return `teinformez:${userId}`;
}

function loadKey(): string | null {
  const key = process.env.LEGAL_HMAC_KEY?.trim();
  if (key && key.length >= 32) return key;
  return null;
}

export function signLegalRequest({ globalUserId, bodyText }: SignArgs): SignedHeaders | null {
  if (process.env.SKIP_LEGAL_HMAC === "1") return null;

  const key = loadKey();
  if (!key) {
    if (process.env.NODE_ENV === "production") {
      throw new Error("LEGAL_HMAC_KEY must be set in production");
    }
    return null;
  }

  const timestamp = Date.now().toString();
  const nonce = randomBytes(16).toString("hex");
  const bodyHash = createHash("sha256").update(bodyText).digest("hex");
  const message = `${APP_SLUG}:${timestamp}:${nonce}:${globalUserId}:${bodyHash}`;
  const signature = createHmac("sha256", key).update(message).digest("hex");

  return {
    "x-app-slug": APP_SLUG,
    "x-app-timestamp": timestamp,
    "x-app-nonce": nonce,
    "x-app-signature": signature,
    "x-user-id": globalUserId,
  };
}
