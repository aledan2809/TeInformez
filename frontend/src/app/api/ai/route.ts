import { createAIRouteHandler } from "ai-router/next";

const handler = createAIRouteHandler({ projectName: "teinformez" });

export const POST = handler.POST;
export const GET = handler.GET;
