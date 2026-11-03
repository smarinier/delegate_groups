# My Groups (delegate_groups)

Nextcloud app giving every user a **My Groups** page:

- the groups they belong to, with their role (member or group admin);
- a **Join a group** button to ask to join the groups opened to membership requests;
- for group admins: the full member list of their groups, pending requests first,
  accept / refuse requests, add any Nextcloud account, remove members.

## Group admins

Group admins are designated by platform admins in *Administration settings › My Groups*,
from the list of groups, and stored in the app table `delegate_groups_admins`.
They are **not** the Nextcloud group admins (subadmins) of *Administration settings › Accounts*:
a subadmin also controls the accounts of their groups (password, email, disabling, deletion),
so being able to add any account to their group would let them take over that account.
A group admin of this app only manages the membership of their groups.

- only platform admins can edit the group admins (routes without `#[NoAdminRequired]`,
  with password confirmation);
- the `admin` group can never have group admins;
- a disabled account is no longer a group admin (it stays listed in the settings, to be removed);
- a group admin cannot remove themself, nor remove another group admin, from the group;
- a group without any group admin cannot be opened, and is never offered for membership requests.

## Options

In *Administration settings › My Groups › General settings* (app config keys of `delegate_groups`):

| Key | Default | Effect |
|-----|---------|--------|
| `users_see_members` | off | the members of a group see its member list (names and roles only: no account id, email nor action) |
| `admin_see_emails`  | off | group admins see the email address of the members of their groups, with a `mailto:` button |

## Membership requests

Platform admins choose the groups open to requests in *Administration settings › My Groups*
(the `admin` group can never be opened).

When a user asks to join a group, each group admin gets a Nextcloud notification and an email
linking to `/apps/delegate_groups/request/{id}`, where they accept or refuse the request.
The first decision applies; the requester is notified of it. A requester can cancel a pending request.

A user added to the group by any other means (by a group admin in the app, or by a platform admin)
closes their pending request.

## Build

```sh
npm ci
npm run build
```

`rollup` is overridden by `@rollup/wasm-node` in `package.json`, as the native rollup binary
does not run on older CPUs.

## API

OCS endpoints under `/ocs/v2.php/apps/delegate_groups/api/v1/`:

| Verb   | Path                    | Who            |
|--------|-------------------------|----------------|
| GET    | `groups`                | any user       |
| GET    | `joinable`              | any user       |
| POST   | `requests`              | any user       |
| GET    | `requests/{id}`         | group admin    |
| DELETE | `requests/{id}`         | requester      |
| POST   | `requests/{id}/accept`  | group admin    |
| POST   | `requests/{id}/refuse`  | group admin    |
| GET    | `members?groupId=`      | group admin, or member with `users_see_members` |
| POST   | `members`               | group admin    |
| DELETE | `members?groupId=&userId=` | group admin |
| GET    | `users?groupId=&search=`| group admin    |
| PUT    | `settings`              | platform admin |
| PUT    | `settings/options`      | platform admin |
| GET    | `admins/users?search=`  | platform admin |
| PUT    | `admins`                | platform admin |
