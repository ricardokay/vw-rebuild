# Phase 3: Editorial workflow

Paste this into Claude Code after Phase 2 is approved.

---

Read CLAUDE.md. Goal: a newsroom workflow where writers submit, editors approve and publish, and contributors get proper public author pages.

1. Install PublishPress (free version). Configure custom statuses: Pitched, In Progress, Needs Images, Ready for Edit, Approved. Enable editorial comments on posts so editors can leave feedback inline.

2. Roles: Contributors can write and submit for review but not publish or upload files; if image upload for contributors is wanted, grant only that capability, nothing more. Authors can publish their own posts (reserved for trusted regulars). Editors can edit and publish everything in their sections. Show me a capability summary table before applying.

3. Recreate author accounts from `inventory/authors.csv`: correct display names, bios, and photos where recovered, all assigned the Contributor role by default. Reassign every migrated post to its correct author so historic bylines and author archive pages are right. Use placeholder emails where unknown and give me a CSV of accounts so I can fix emails later.

4. Author pages: design an author archive template in the child theme with photo, bio, role, and their article feed, in keeping with the magazine design.

5. Notifications: configure PublishPress so editors are notified when a post reaches Ready for Edit, and writers are notified on editorial comments or publication.

6. Walk me through the workflow once as a test: create a dummy contributor, submit a draft, leave an editorial comment as editor, approve, publish, then delete the test content. Update `PROGRESS.md` and stop for review.
