# Flaportum

Flaportum is a commandline application to migrate existing forums to Flarum.

This project is very much in the Proof of Concept stage. It needs refactoring, and it currently only supports exporting from plugin support forums hosted on the OctoberCMS website.


## Usage

Flaportum comes with three main commands in its arsenal:

* `php flaportum export` will save users and discussions from a supported forum
* `php flaportum import` lets you select a previously-exported forum to migrate into a Flarum install
* `php flaportum run` goes through the whole process for a complete migration in one command

In addition to those, you can also run `php flaportum cache:clear` to nuke all exported forum data.


## Creating tags

A handful of pull requests were merged into [flarum/flarum-ext-tags](https://github.com/flarum/flarum-ext-tags) to fix some bugs and enable easier creation of tags.

Until they appear in a new release (current version at time of writing is **0.1.0-beta.7**) you'll need to manually clone master in your `vendor/flarum/` directory.


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


## User registration dates

Similar to post times, Flarum currently doesn't enable setting the registration date for users via the API.

To preserve join dates, you'll need to edit `vendor/flarum/core/src/Core/Command/RegisterUserCommand.php`.

First, add `use DateTime;` at the top, then update the `handle` method like so:

```diff
             $password = $password ?: str_random(20);
         }

         $user = User::register($username, $email, $password);
+
+        if ($actor->isAdmin() && $date = array_get($data, 'attributes.join_time')) {
+            $user->join_time = new DateTime($date);
+        }

         // If a valid authentication token was provided, then we will assign
         // the attributes associated with it to the user's account. If this
         // includes an email address, then we will activate the user's account
         // from the get-go.
         if (isset($token)) {
             foreach ($token->payload as $k => $v) {
```
