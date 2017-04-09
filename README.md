# Flaportum

Flaportum is a commandline application to migrate existing forums to Flarum.

It isn't currently of much use, unless you happen to be an author of an OctoberCMS plugin and want to migrate your support forum.


## Core Hacks

Some things won't work without modifying your Flarum install in some way.

Before importing, follow this section to make any necessary edits if you want full functionality.

### Setting post timestamps

Flarum allows setting post timestamps, but only for admin users. You could set everybody as admin, but that's not feasible for a large number of users.

Open `vendor/flarum/core/src/Core/Command/PostReplyHandler.php`, find [this block](https://github.com/flarum/core/blob/2140619c0ba619602b90ef9526b1278335f7f3d8/src/Core/Command/PostReplyHandler.php#L94-L96) and change the following:

```diff
-        if ($actor->isAdmin() && ($time = array_get($command->data, 'attributes.time'))) {
+        if ($time = array_get($command->data, 'attributes.time')) {
```

It's probably a really bad idea to allow anyone to set the post time normally, so you should **revert this change** after importing.
