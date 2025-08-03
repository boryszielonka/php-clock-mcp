# PHP Clock MCP - quick proof of concept
I used [Symfony MCP Bundle](https://github.com/symfony/ai/blob/main/src/mcp-bundle/README.md) and [Symfony Clock](https://github.com/symfony/clock)
for creating lightweight MCP server.

It is ~~**WORK IN PROGRESS**~~ ABANDONED, but it is usable with some config tweaks.

## UPDATE:
Claude can execute JS code, so the easier way is to execute JavaScript,
for example `console.log(new Date().toLocaleString());`.
It seems to be a bit slower than MCP tool though.
