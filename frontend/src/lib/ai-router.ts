import { AIRouter, getProjectPreset } from "ai-router";
import type {
  AIRequest,
  AIResponse,
  AIMessage,
  AIProviderID,
  AIProviderSelection,
} from "ai-router";

// ── TeInformez AI Router singleton ───────────────────────

const preset = getProjectPreset("teinformez");

const router = new AIRouter({
  ...preset,
  maxInputChars: 6000,
  retryDelayMs: 2000,
  maxRetries: 2,
});

// ── Public API ───────────────────────────────────────────

export type { AIRequest, AIResponse, AIMessage, AIProviderID, AIProviderSelection };

/**
 * Route an AI request through the TeInformez preset.
 * Free providers first, fallback to Claude/OpenAI.
 */
export async function routeAI(
  request: Omit<AIRequest, "messages"> & { messages: AIMessage[] }
): Promise<AIResponse> {
  return router.chat(request);
}

/**
 * Shorthand: send a single prompt with optional system message.
 */
export async function routeAISimple(opts: {
  prompt: string;
  system?: string;
  provider?: AIProviderSelection;
  maxTokens?: number;
  temperature?: number;
  jsonMode?: boolean;
  languageHint?: AIRequest["languageHint"];
  taskHint?: AIRequest["taskHint"];
}): Promise<AIResponse> {
  const messages: AIMessage[] = [];
  if (opts.system) {
    messages.push({ role: "system", content: opts.system });
  }
  messages.push({ role: "user", content: opts.prompt });

  return router.chat({
    messages,
    provider: opts.provider,
    maxTokens: opts.maxTokens,
    temperature: opts.temperature,
    jsonMode: opts.jsonMode,
    languageHint: opts.languageHint,
    taskHint: opts.taskHint,
  });
}

/** Get health status of all configured providers */
export function getRouterHealth() {
  return router.getHealth();
}

/** Get providers that have API keys configured */
export function getAvailableProviders() {
  return router.getAvailableProviders();
}

/** The raw router instance (for advanced use) */
export { router };
