# Flaportum

Flaportum is a commandline application to migrate existing forums to Flarum.

It isn't currently of much use, unless you happen to be an author of an OctoberCMS plugin and want to migrate your support forum.


## Setting post timestamps

Flarum allows setting post timestamps, but only for admin users, so you'll need to fix that.

You could set everybody as admin to solve the create times, but that's not feasible for a large number of users.
It also won't help with edit times, which can't be set when you create a post.

Note: This change affects start times for both posts *and* discussions.

Open `vendor/flarum/core/src/Core/Command/PostReplyHandler.php`, find [this block](https://github.com/flarum/core/blob/2140619c0ba619602b90ef9526b1278335f7f3d8/src/Core/Command/PostReplyHandler.php#L94-L96) and change the following:

```diff
             $command->ipAddress
         );

-        if ($actor->isAdmin() && ($time = array_get($command->data, 'attributes.time'))) {
+        if ($time = array_get($command->data, 'attributes.time')) {
             $post->time = new DateTime($time);
         }

+        if ($editTime = array_get($command->data, 'attributes.edit_time')) {
+            $post->edit_time = new DateTime($editTime);
+            $post->edit_user_id = array_get($command->data, 'attributes.edit_user_id', $actor->id);
+        }

         $this->events->fire(
             new PostWillBeSaved($post, $actor, $command->data)
         );
```

It's probably a really bad idea to allow anyone to set the post time normally, so you should **revert this change** after importing.
