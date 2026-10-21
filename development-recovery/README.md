# Retained Pro Analytics development history

The bundle `pro-company-reporting-through-5ce0cbb.bundle` retains the original 76 development commits through `5ce0cbb6515ca116a820f42cb9e95055c45eca0c`, including their original IDs, author metadata, trees and blobs. It requires ancestor `996ff309befd0c121217e7f5ec32d6ea28ab7696`, which is already in this repository's master history. The current source snapshot was imported through the connected GitHub app because the temporary coding workspace could not authenticate a command-line push.

This is recovery material, not the starting point for new work. Use the latest `master` and `pro-platform/plugin/westgate-practicum-pro` for development and packaging.

To inspect the retained history in an existing clone:

```sh
git bundle verify development-recovery/pro-company-reporting-through-5ce0cbb.bundle
git fetch development-recovery/pro-company-reporting-through-5ce0cbb.bundle refs/heads/feature/pro-company-reporting:refs/heads/recovered/pro-company-reporting
```

Do not reset master to the recovered branch: master also retains newer independent calendar changes.
