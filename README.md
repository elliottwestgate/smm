# smm

## Projects

- [Social Media Magnet Pro book editions](publishing/README.md): current publishing master and retained prior edition.
- [SMM .net and Pro work history](SMM%20Westgate%20Analytics/docs/SMM-Net-and-Pro-Work-History.md): completed work, validation limits, site links, and open follow-ups.
- [SMM Westgate Analytics](SMM%20Westgate%20Analytics/README.md): Practicum analytics replacement scope, methodology, research, and sanitized legacy reference code for .net, Pro, and a future licensed Westgate offering.

## Current Pro Analytics source

- Maintained plugin: [`pro-platform/plugin/westgate-practicum-pro`](pro-platform/plugin/westgate-practicum-pro), version **0.6.25-preview**.
- Start future development from the latest `master`. Follow [`pro-platform/AGENTS.md`](pro-platform/AGENTS.md); reconcile deployed differences before updating a website.
- Build releases from clean, committed, published source with `python pro-platform/build_plugin.py --output /absolute/output.zip`. Each package includes its commit and asset hashes. Run the relevant regression checks and staging verification before deployment.
- The imported Pro source matches local commit `5ce0cbb6515ca116a820f42cb9e95055c45eca0c`. Its original development history is retained in [`development-recovery`](development-recovery/README.md).
