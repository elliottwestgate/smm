# Pro Analytics change and release rules

- Make plugin changes in this maintained repository first. Do not modify plugin PHP, JavaScript, or CSS through a production WordPress file editor. Browser inspection of deployed source is read-only.
- Do not treat a successful live change as a completed source change. Reconcile any discovered live differences into source, review the diff, run relevant regressions, and commit and push them before packaging.
- Preserve company boundaries, existing saved Reviews, encrypted document versions, provider permissions, and current site configuration.
- Build installation ZIPs only with `python pro-platform/build_plugin.py --output /absolute/output.zip` from a clean committed source tree. The ZIP records the source commit and per-file hashes.
- Before installation, compare the target with the release source and account for any independent admin/navigation overlays or later changes. Test on staging first. Never replace a newer installation with an older full package to apply a small fix.
- Use the normal WordPress plugin update/install flow for a tested package. A user-authorized deployment is still required; do not use the file editor as a deployment shortcut.
