=== WPSuperQuiz ===
Contributors: Superlevel
Donate Link: http://www.superlevel.de/wordpress/wpsuperquiz
Tags: quiz, fun, superlevel
Requires at least: 2.5
Tested up to: 2.9.1
Stable tag: 1.2.2

Use your Wordpress Blog as a quiz machine!

== Description ==

WPSuperQuiz offers you the opportunity to create a quiz from your normal blog posts. You write a post with one or more questions and your visitors can submit anwers via the comment function. Each submitted suggestion is checked for the correct solution. In case someone submitted the right answer, the winner will be named in the initial quiz post and a link to the correct comment is created. To keep the quiz exciting, all answers will be masked with asterisks (by default), which will be removed when the quiz has been finished.

== Screenshots ==

1. View of Configuration Options
1. Creating a Quiz

== Installation ==

1. Copy folder `wpsuperquiz` to your `/wp-content/plugins/` directory
1. Activate the plugin in your admin-panel ('Plugins')
1. done

== Frequently Asked Questions ==

None -- so far.

== Activation ==

* Admin-Panel — Posts — *Add new*
* Under your post, check *“Mark post as quiz”*
* *Mask answers* (optional) (If this option is checked, all suggestions will be masked with asterisks until the quiz is finished.)
* *First right answer wins* (optional) (**Deactivate** this to define a deadline. The winner will be randomly identified in a lottery when the deadline has been reached.)
* Enter the right answer(s) in the input field and separate them with commas (E.g.: Answer 1, Answer 2, Answer 3)
* Letter case can be ignored.

== Integration into a blog post ==

* `[quiz:finish]` is a placeholder for the final results and can be inserted at any position within the blog post.
* `[quiz:rules]` automatically adds an information text to the blog post. There is a default text, which can be edited to meet your needs if necessary.

The following blog post structure is recommended:

`[quiz:finish]

Article / Questions

[quiz:rules]`

== Answers ==

Visitors can submit answers via the comment function, using the following syntax:

`[quiz: Answer]`

`[quiz: Answer to Question 1, Answer to Question 2, Answer to Question 3]`

== Settings (optional) ==

You can change several settings in the Admin Configuration panel ('Plugins' — 'WPSuperQuiz').

**Results Message**

This is the final message that shows the results when the quiz is over. It can be integrated in a post by using `[quiz:finish]`.

**Rules**

This displays the rules of the quiz. Integrate it in the post via `[quiz:rules]`. The information text contains a link to Superlevel.de, which could of course be removed. However, we would appreciate it if you keep the link as a tribute to our work.

**CSS**

Here you can edit the CSS to format the quiz post and comments.
*Attention:* To edit the style sheet here, write access to the file `plugins/wpsuperquiz/wp-superquiz-styles.css` is needed (chmod 666).