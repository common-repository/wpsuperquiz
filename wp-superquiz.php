<?php
/*
Plugin Name: WPSuperQuiz
Plugin URI: http://www.superlevel.de/wordpress/wpsuperquiz
Description: Use your Wordpress Blog as a quiz machine!
Author: Matthias Kunze
Version: 1.2.3
Author URI: http://www.superlevel.de
*/

if (!class_exists('WPSuperQuiz')) {
        class WPSuperQuiz {

                var $quizMetaKey = 'wpsuperquiz';

                // temp variable
                var $quiz = array();

                /**
                 * for php 4
                 */
                function WPSuperQuiz() {
                        $this->__construct();
                }

                /**
                 * add some actions and filters
                 */
                function __construct() {
                        $basename = basename(dirname(__FILE__));

                        $this->pluginDir = get_option('siteurl') . '/wp-content/plugins/' . $basename;

                        register_activation_hook(__FILE__, array(&$this, 'activateWPSuperQuiz'));
                        register_deactivation_hook(__FILE__, array(&$this, 'deactivateWPSuperQuiz'));

                        add_action('admin_menu', array(&$this, 'addWPSuperQuizBox'));
                        add_action('wp_head', array(&$this, 'addWPSuperQuizCSS'));
                        add_action('admin_head', array(&$this, 'addWPSuperQuizCSS'));
                        add_action('save_post', array(&$this, 'saveWPSuperQuizOption'));
                        add_action('comment_post', array(&$this, 'checkWPSuperQuizSolved'));
                        add_filter('comment_text', array(&$this, 'parseWPSuperQuizContent'));
                        add_filter('the_content', array(&$this, 'parseWPSuperQuizPlaceholder'));

                        add_action('wpsuperquiz_schedule', array(&$this, 'checkWPSuperQuizComments'));

                        // feed
                        add_filter('comment_text_rss', array(&$this, 'parseWPSuperQuizContentFeed'));

                        // set options
                        $this->setOptions();

                        // I18n
                        load_plugin_textdomain('wpsuperquiz', 'wp-content/plugins/' . $basename . '/languages', $basename . '/languages');
                }

                /**
                 * on plugin activation add options
                 */
                function activateWPSuperQuiz() {
                        add_option('WPSuperQuiz', array(
                                'before_suggestion' => __('Lösungsvorschlag: ', 'wpsuperquiz'),
                                'before_solution'   => __('Lösung: ', 'wpsuperquiz'),
                                'success_message'   => __('<strong>Quiz beendet!</strong><br/>Gratulation &mdash; <a href="[COMMENT_LINK]">[COMMENT_AUTHOR]</a> hat das Quiz gewonnen.', 'wpsuperquiz'),
                                'rules_message'     =>
                                    __('Lösungsvorschläge bitte in folgender Form als Kommentar übermitteln:<br/>[quiz:Antwort] bzw. [quiz:Antwort auf Frage 1, Antwort auf Frage 2, ...]<br/><br/>', 'wpsuperquiz')
                                  . __('Pro Kommentar bitte nur einen Lösungsvorschlag abschicken. Gegebenfalls (je nach Einstellung) werden die Lösungsvorschläge maskiert (******) dargestellt. Die Maskierungen werden automatisch aufgehoben, sobald die richtige Antwort übermittelt wurde. Groß- und Kleinschreibung kann ignoriert werden.<br/><br/>', 'wpsuperquiz')
                                  . __('Dieses Quiz wurde mit dem Plugin <a href="http://www.superlevel.de/wordpress/wpsuperquiz">WPSuperQuiz</a> von <a href="http://www.superlevel.de">Superlevel</a> realisiert.', 'wpsuperquiz')
                        ));
                }

                /**
                 * deactivate quiz and remove options
                 */
                function deactivateWPSuperQuiz() {
                        delete_option('WPSuperQuiz');
                }

                /**
                 * add admin panel at the bottom of the post feld
                 */
                function addWPSuperQuizBox() {
                        add_submenu_page('plugins.php', __('WPSuperQuiz Konfiguration', 'wpsuperquiz'), 'WPSuperQuiz', 8, basename(__FILE__), array(&$this, 'configWPSuperQuiz'));
                        add_filter('plugin_action_links', array(&$this, 'filterPluginActions'), 10, 2);
                        add_meta_box('wpsuperquiz_field', 'WPSuperQuiz', array(&$this, 'addWPSuperQuizInput'), 'post', 'normal', 'high');
                }

                /**
                 * set options
                 */
                function setOptions() {
                        $options = get_option('WPSuperQuiz');
                        $this->pattern = "\[quiz:([^]]+)\]";
                        $this->successMessage = $options['success_message'];
                        $this->beforeSuggestion = $options['before_suggestion'];
                        $this->beforeSolution = $options['before_solution'];
                        $this->rulesMessage = $options['rules_message'];
                }

                /**
                 * add settings link to plugin list
                 */
                function filterPluginActions($links, $file) {
                        static $this_plugin;
                        if (!$this_plugin) {
                                $this_plugin = plugin_basename(__FILE__);
                        }
                        if ($file == $this_plugin) {
                            	$settings_link = '<a href="plugins.php?page=wp-superquiz.php">' . __('Einstellungen') . '</a>';
                            	array_unshift($links, $settings_link); // before other links
                        }
                        return $links;
        		}

                /**
                 * add css
                 */
                function addWPSuperQuizCSS() {
                        echo '<link rel="stylesheet" href="' . $this->pluginDir . '/wp-superquiz-styles.css" type="text/css" media="screen"  />';
                }

                /**
                 * show configuration page
                 */
                function configWPSuperQuiz() {
                        $cssFile = dirname(__FILE__) . '/wp-superquiz-styles.css';

                        if (isset($_POST['submit'])) {
                                $validNonce = wp_verify_nonce($_REQUEST['_wpnonce'], 'wp-superquiz-settings');
                                if ($validNonce === false) {
                                        die("Security problem!");
                                }

                                $beforeSuggestion = stripslashes($_POST['before_suggestion']);
                                $beforeSolution = stripslashes($_POST['before_solution']);
                                $successMessage = stripslashes(trim($_POST['success_message']));
                                $rulesMessage = stripslashes(trim($_POST['rules_message']));

                                // remove tags
                                $beforeSuggestion = strip_tags($beforeSuggestion);
                                $beforeSolution = strip_tags($beforeSolution);

                                // new options
                                $options = array(
                                        'before_suggestion' => $beforeSuggestion,
                                        'before_solution' => $beforeSolution,
                                        'success_message' => $successMessage,
                                        'rules_message' => $rulesMessage
                                );

                                update_option('WPSuperQuiz', $options);

                                // css
                                $newCss = stripslashes(trim($_POST['style_sheet']));
                				if (is_writeable($cssFile)) {
                    					$f = fopen($cssFile, 'w+');
                    					fwrite($f, $newCss);
                    					fclose($f);
                				}
                        }
                        else {
                                $options = get_option('WPSuperQuiz');
                        }

                        $disabled = ' disabled="disabled"';
                        if (!is_file($cssFile)) {
                                $cssMsg = sprintf(__('<em>%s</em> wurde nicht gefunden!', 'wpsuperquiz'), 'wp-superquiz-styles.css');
                        }
                        else if (!is_readable($cssFile)) {
                                $cssMsg = sprintf(__('<em>%s</em> konnte nicht gelesen werden!', 'wpsuperquiz'), 'wp-superquiz-styles.css');
                        }
                        else if (!is_writeable($cssFile)) {
                                $cssMsg = sprintf(__('<em>%s</em> ist nicht beschreibbar!', 'wpsuperquiz'), 'wp-superquiz-styles.css');
                        }
                        else {
                                $cssMsg = sprintf(__('<em>%s</em> kann direkt bearbeitet werden.</small>', 'wpsuperquiz'), 'wp-superquiz-styles.css');
                                $disabled = '';
                        }

                        if (filesize($cssFile) > 0) {
                				$f = fopen($cssFile, 'r');
                				$content = fread($f, filesize($cssFile));
                				$content = wp_specialchars($content);
            			}
            			else {
                                $content = '';
                        }

                        $options['success_message'] = wp_specialchars($options['success_message']);
                        $options['rules_message'] = wp_specialchars($options['rules_message']);
                        ?>
                        <div class="wrap">
				            <h2><?php _e('WPSuperQuiz Konfiguration', 'wpsuperquiz'); ?></h2>
				            <form action="" method="post" id="quiz-conf">
				            <?php wp_nonce_field('wp-superquiz-settings'); ?>
				            <table class="form-table">
                            <tr>
                                <td colspan="2"><h3><?php _e('Antwort-Kommentare', 'wpsuperquiz'); ?></h3></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('Lösungsvorschlag', 'wpsuperquiz'); ?> <small>(<?php _e('kein HTML', 'wpsuperquiz'); ?>)</small></th>
                                <td><input type="text" name="before_suggestion" value="<?php echo $options['before_suggestion']; ?>" size="65" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('Lösung', 'wpsuperquiz'); ?> <small>(<?php _e('kein HTML', 'wpsuperquiz'); ?>)</small></th>
                                <td><input type="text" name="before_solution" value="<?php echo $options['before_solution']; ?>" size="65" /></td>
                            </tr>
                            <tr>
                                <td colspan="2"><h3><?php _e('Quiz-Beitrag', 'wpsuperquiz'); ?></h3></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('Erfolgsmeldung', 'wpsuperquiz'); ?> <small>(<?php _e('HTML erlaubt', 'wpsuperquiz'); ?>)</small>
                                <br/><br/>
                                <small><?php _e('Platzhalter:', 'wpsuperquiz'); ?><br/>
                                [COMMENT_LINK] = <?php _e('Link zum Kommentar', 'wpsuperquiz'); ?><br/>
                                [COMMENT_AUTHOR] = <?php _e('Autor des Kommentars', 'wpsuperquiz'); ?><br/>
                                <br/>
                                <?php _e('Einbindung in Artikel:', 'wpsuperquiz'); ?><br/>
                                [quiz:finish]</small></th>
                                <td><textarea cols="63" rows="10" name="success_message"><?php echo $options['success_message']; ?></textarea></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('Spielregeln', 'wpsuperquiz'); ?> <small>(<?php _e('HTML erlaubt', 'wpsuperquiz'); ?>)</small>
                                <br/><br/>
                                <small><?php _e('Einbindung in Artikel:', 'wpsuperquiz'); ?><br/>
                                [quiz:rules]</small></th>
                                <td><textarea cols="63" rows="10" name="rules_message"><?php echo $options['rules_message']; ?></textarea></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('CSS', 'wpsuperquiz'); ?><br/>
                                <br/>
                                <?php echo $cssMsg; ?></th>
                                <td><textarea<?php echo $disabled; ?> cols="63" rows="20" name="style_sheet"><?php echo $content; ?></textarea></td>
                            </tr>
                            </table>
                            <p class="submit">
                                <input name="submit" type="submit" class="button-primary" value="<?php _e('Änderungen übernehmen', 'wpsuperquiz'); ?>" />
                            </p>
				            </form>
				        </div>
				        <?php
                }

                /**
                 * show quiz admin panel on post page
                 */
                function addWPSuperQuizInput() {
            			global $post, $wpdb;

            			$answers = '';
            			$mask = $first = true;

            			$finalDate = date("d:m:Y:H:i", time());
            			$checked = $this->checkWPSuperQuiz($post->ID);

            			if ($checked === true) {
            			        $answers = implode(', ', $this->quiz['solution']);
                                $mask = (bool)$this->quiz['mask'];
                                // for older version
                                $first = (!isset($this->quiz['first']) ? true : (bool)$this->quiz['first']);
            			}

            			if ($first === false) {
            			        $finalDate = $this->quiz['deadline'];
            			}

            			$finalDateTmp = explode(":", $finalDate);
    			        $finalDate = array(
                                "day" => $finalDateTmp[0],
                                "month" => $finalDateTmp[1],
                                "year" => $finalDateTmp[2],
                                "hour" => $finalDateTmp[3],
                                "minute" => $finalDateTmp[4]
                        );
                        ?>
                        <p>
                            <input<?php if ($checked === true) : ?> checked="checked"<?php endif; ?> type="checkbox" id="wpsuperquiz_checked" name="wpsuperquiz_checked" value="1" />
                            <label for="wpsuperquiz_checked"><?php _e('Beitrag als Quiz markieren', 'wpsuperquiz'); ?></label>
                        </p>
                        <div id="wpsuperquiz_input"<?php if ($checked === false) : ?> style="display: none;"<?php endif; ?>>
                            <p><input<?php if ($mask === true) : ?> checked="checked"<?php endif; ?> type="checkbox" id="wpsuperquiz_mask" name="wpsuperquiz_mask" value="1" /> <label for="wpsuperquiz_mask"><?php _e('Antworten maskieren', 'wpsuperquiz'); ?></label></p>
                            <p><input<?php if ($first === true) : ?> checked="checked"<?php endif; ?> type="checkbox" id="wpsuperquiz_first" name="wpsuperquiz_first" value="1" /> <label for="wpsuperquiz_first"><?php _e('Erste richtige Antwort gewinnt', 'wpsuperquiz'); ?></label></p>
                            <div id="wpsuperquiz_deadline"<?php if ($first === true) : ?> style="display: none;"<?php endif; ?>>
                                <p>
                                    <span><?php _e('Quiz wird beendet am', 'wpsuperquiz'); ?>:</span> <select name="wpsuperquiz_date[day]">
                                        <?php for ($i = 1; $i <= 31; $i++) :
                                                 $day = ($i < 10 ? '0' . $i : $i);
                                                 $selected = ($finalDate['day'] == $day ? ' selected="selected"' : '');
                                        ?>
                                        <option<?php echo $selected; ?> value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select name="wpsuperquiz_date[month]">
                                        <option<?php echo ($finalDate['month'] == '01' ? ' selected="selected"' : ''); ?> value="01"><?php _e('Januar', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '02' ? ' selected="selected"' : ''); ?> value="02"><?php _e('Februar', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '03' ? ' selected="selected"' : ''); ?> value="03"><?php _e('März', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '04' ? ' selected="selected"' : ''); ?> value="04"><?php _e('April', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '05' ? ' selected="selected"' : ''); ?> value="05"><?php _e('Mai', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '06' ? ' selected="selected"' : ''); ?> value="06"><?php _e('Juni', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '07' ? ' selected="selected"' : ''); ?> value="07"><?php _e('Juli', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '08' ? ' selected="selected"' : ''); ?> value="08"><?php _e('August', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '09' ? ' selected="selected"' : ''); ?> value="09"><?php _e('September', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '10' ? ' selected="selected"' : ''); ?> value="10"><?php _e('Oktober', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '11' ? ' selected="selected"' : ''); ?> value="11"><?php _e('November', 'wpsuperquiz'); ?></option>
                                        <option<?php echo ($finalDate['month'] == '12' ? ' selected="selected"' : ''); ?> value="12"><?php _e('Dezember', 'wpsuperquiz'); ?></option>
                                    </select>
                                    <select name="wpsuperquiz_date[year]">
                                        <?php for ($i = date('Y'); $i <= (date('Y') + 5); $i++) :
                                                 $selected = ($finalDate['year'] == $i ? ' selected="selected"' : '');
                                        ?>
                                        <option<?php echo $selected; ?> value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span>@</span> <input type="text" class="wpsq-short-input" value="<?php echo $finalDate['hour']; ?>" name="wpsuperquiz_date[hour]" /> <span>:</span> <input type="text" class="wpsq-short-input" value="<?php echo $finalDate['minute']; ?>" name="wpsuperquiz_date[minute]" />
                                </p>
                                <p><small>(<?php _e('Sollten bis zu diesem Zeitpunkt mehrere richtige Antworten vorliegen, wird der Gewinner automatisch in einem Losverfahren ermittelt und genannt.', 'wpsuperquiz'); ?>)</small></p>
                            </div>
                            <p><?php _e('Antwort(en)', 'wpsuperquiz'); ?>:</p>
                            <p><input type="text" id="wpsuperquiz_answer" name="wpsuperquiz_answer" value="<?php echo $answers; ?>" style="width: 98%" /></p>
                            <p><?php _e('Mehrere Antworten mit Kommata trennen.', 'wpsuperquiz'); ?> <small>(<?php _e('z.B.: Antwort 1, Antwort 2, Antwort 3', 'wpsuperquiz'); ?>)</small></p>
                        </div>
                        <style type="text/css">
                        .wpsq-short-input {
                          width: 30px;
                          font-size: 12px;
                        }
                        </style>
                        <script type="text/javascript">
                        (function($) {
                                $('#wpsuperquiz_checked').bind('change', function () {
                                        if (this.checked === true) {
                                                $('#wpsuperquiz_input').fadeIn();
                                        }
                                        else {
                                                $('#wpsuperquiz_input').fadeOut();
                                        }
                                });
                                $('#wpsuperquiz_first').bind('change', function () {
                                        if (this.checked === false) {
                                                $('#wpsuperquiz_deadline').fadeIn();
                                        }
                                        else {
                                                $('#wpsuperquiz_deadline').fadeOut();
                                        }
                                });
                        })(jQuery);
                        </script>
                        <style type="text/css">
                        #wpsuperquiz_input p small {
                          color: #888;
                        }
                        </style>
            			<?php

        		}

        		/**
        		 * is the current post a superquiz?
        		 * @param  int  id of the posting
        		 * @return bool
        		 */
        		function checkWPSuperQuiz($postID) {
        		        $quiz = get_post_meta($postID, $this->quizMetaKey, true);
        		        if ($quiz != '') {
        		                // for old quizzes
        		                if (!isset($quiz['non_winner'])) {
        		                        $quiz['non_winner'] = array();
        		                }
        		                $this->quiz = $quiz;
        		                return true;
        		        }
        		        return false;
        		}

        		/**
        		 * is the new posting a quiz save answers as meta
        		 * @param int id of the new post
        		 */
                function saveWPSuperQuizOption($postID) {
                        /*
                        if (wp_is_post_revision($postID) !== false) {
                                return true;
                        }
                        */
                        $validStatusses = array('publish', 'private', 'future');
                        if (!in_array(get_post_status($postID), $validStatusses)) {
                                return true;
                        }

                        // get old meta
                        $oldMeta = get_post_meta($postID, $this->quizMetaKey, true);
                        // defaults
                        $isSolved = 0;
                        $nonWinner = array();



                        // don't override is_solved and non_winner
                        // necessary on post edit things
                        if (isset($oldMeta['is_solved'])) {
                                $isSolved = $oldMeta['is_solved'];
                        }
                        if (isset($oldMeta['non_winner'])) {
                                $nonWinner = $oldMeta['non_winner'];
                        }

                        // remove old superquiz meta
                        delete_post_meta($postID, $this->quizMetaKey);

                        // it's a quiz
                        if (isset($_POST['wpsuperquiz_checked']) && $_POST['wpsuperquiz_checked'] == '1') {
                                // trim answers
                                $answers = explode(',', trim($_POST['wpsuperquiz_answer']));
                                foreach ($answers as $index => $ans) {
                                        $answers[$index] = trim($ans);
                                }

                                // add superquiz
                                if (count($answers) > 0) {
                                        // options
                                        $mask = (isset($_POST['wpsuperquiz_mask']) && $_POST['wpsuperquiz_mask'] == '1');
                                        $first = (isset($_POST['wpsuperquiz_first']) && $_POST['wpsuperquiz_first'] == '1');
                                        // get deadline
                                        $deadline = ($first === false && $isSolved == 0) ? implode(':', $_POST['wpsuperquiz_date']) : false;
                                        $value = array(
                                                'solution' => $answers,
                                                'mask' => $mask,
                                                'first' => $first,
                                                'deadline' => $deadline,
                                                'is_solved' => $isSolved,
                                                'non_winner' => $nonWinner
                                        );
                                        add_post_meta($postID, $this->quizMetaKey, $value);

                                        // schedule deadline
                                        if ($deadline !== false) {
                                                $d = $_POST['wpsuperquiz_date'];
                                                $scheduleTime = mktime($d['hour'], $d['minute'], 0, $d['month'], $d['day'], $d['year']);
                                                wp_schedule_single_event($scheduleTime, 'wpsuperquiz_schedule', array($postID));
                                        }
                                }
                        }
        		}

        		/**
        		 * if the new comment is the right answer of the quiz save it and close quiz
        		 * @param int id of the comment
        		 */
                function checkWPSuperQuizSolved($commentID) {
        		        $comment = get_comment($commentID);
        		        $content = $comment->comment_content;
        		        $postID = $comment->comment_post_ID;

        		        $isSuperquiz = $this->checkWPSuperQuiz($postID);
        		        // is quiz and not solved
        		        if ($isSuperquiz === true && $this->quiz['is_solved'] == 0) {
                                $answers = $this->parseWPSuperQuizCommentTag($content);
                                if ($answers !== false && (bool)$this->quiz['first'] === true) {
                                        // check answers
                                        $isAnswer = $this->checkWPSuperQuizAnswer($answers);
                                        if ($isAnswer === true) {
                                                // quiz is solved
                                                $this->saveWPSuperQuizWinner($postID, $commentID);
                                        }
                                }
        		        }

        		        return false;
        		}

        		/**
        		 * check the given answers
        		 * @param  array answers
        		 * @return bool
        		 */
                function checkWPSuperQuizAnswer($answers) {
        		        $solution = $this->quiz['solution'];
        		        $correctAnswers = 0;

                        foreach ($solution as $index => $res) {
                                $sol = strtolower($res);
                                $spellings = explode('|', $sol);
                                $ans = strtolower($answers[$index]);
                                foreach ($spellings as $spelling) {
                                        if ($ans == $spelling) {
                                                $correctAnswers++;
                                                break;
                                        }
                                }
                        }

                        // are all answers correct?
                        return ($correctAnswers == count($solution));
        		}

        		/**
        		 * parse quiz tags of a comment
                 * @param str  comment content
                 * @param bool replace tags
        		 */
        		function parseWPSuperQuizCommentTag($content, $replace = false) {
                        // only one quiz tag allowed
                        if (preg_match('/' . $this->pattern . '/i', $content, $matches)) {
                                // split answers
                                $answer = explode(',', $matches[1]);
                                $answers = array();

                                for ($i = 0; $i < count($answer); $i++) {
                                        $ans = trim($answer[$i]);
                                        $answers[] = $ans;
                                }

                                if ($replace === true) {
                                        $before = '<span class="superquiz-before-suggestion">' . $this->beforeSuggestion . '</span>';
                                        if ($this->quiz['is_solved'] == $this->currentCommentID || isset($this->quiz['non_winner'][$this->currentCommentID])) {
                                                $before = '<span class="superquiz-before-solution">' . $this->beforeSolution . '</span>';
                                        }
                                        if ($this->quiz['is_solved'] == 0 && (bool)$this->quiz['mask'] === true) {
                                                $answers = array('******');
                                        }
                                        return str_replace($matches[0], $before . implode(', ', $answers), $content);
                                }
                                else {
                                        return $answers;
                                }
                        }
                        // nothing found
                        return ($replace === true) ? $content : false;
        		}

        		/**
        		 * parse quiz placeholder for winning message in the posting
        		 * @param  str content of posting
        		 * @return str parsed content
        		 */
                function parseWPSuperQuizPlaceholder($content) {
                        global $post, $wp_version;

                        $isSuperquiz = $this->checkWPSuperQuiz($post->ID);
                        // xhtml pattern
        		        if ($isSuperquiz === true && preg_match_all('/[<p>]*' . $this->pattern . '[<\/p>]*/is', $content, $matches)) {
        		              foreach ($matches[1] as $index => $match) {
        		                      $match = trim($match);
        		                      $replace = '';
        		                      if ($match == 'finish') {
                		                      if ($this->quiz['is_solved'] != 0) {
                    		                          $commentID = intval($this->quiz['is_solved']);
                        		                      $comment = get_comment($commentID);
                                                      $commentAuthor = $comment->comment_author;
                        		                      $commentLink = get_comment_link($comment->comment_ID);

                        		                      // add comment-id on older wp installations
                        		                      if (version_compare($wp_version, "2.7", "<")) {
                                            		          $commentLink .= $comment->comment_ID;
                                            		  }

                        		                      $message = str_replace(array(
                                                            '[COMMENT_AUTHOR]',
                                                            '[COMMENT_LINK]',
                                                            /*'[CORRECT_ANSWER]'*/
                                                      ), array(
                                                            wp_specialchars($commentAuthor),
                                                            $commentLink,
                                                            /*$answer*/
                                                      ), $this->successMessage);
                        		                      $replace = '<div class="superquiz-finish">' . $message . '</div>';
                		                      }
                		              }
                		              else if ($match == 'rules') {
                		                      $replace = '<div class="superquiz-rules">' . $this->rulesMessage . '</div>';
                		              }
                		              $content = str_replace($matches[0][$index], $replace, $content);
        		              }
        		        }

        		        return $content;
        		}

                /**
                 * if the post is a quiz parse quiz-tags in comments
                 * @param string content of the comment
                 */
                function parseWPSuperQuizContent($content = '') {
                        global $comment;

                        $postID = $comment->comment_post_ID;

                        $isSuperquiz = $this->checkWPSuperQuiz($postID);
                        // is quiz
                        if ($isSuperquiz === true) {
                                $this->currentCommentID = $comment->comment_ID;
                                $content = $this->parseWPSuperQuizCommentTag($content, true);
                        }

                        return $content;
        		}

        		/**
                 * parse comment content for comment feed
                 * @param string content of the comment
                 */
                function parseWPSuperQuizContentFeed($content = '') {
                        $content = $this->parseWPSuperQuizContent($content);
                        $content = wp_specialchars($content);
                        return $content;
        		}

        		/**
        		 * check scheduled quiz
        		 * @param array $args
        		 */
                function checkWPSuperQuizComments($postID) {
                        global $wpdb;

                        $this->checkWPSuperQuiz($postID);

                        // get comments
                        $comments = $wpdb->get_results(
                                "SELECT comment_ID,
                                        comment_content,
                                        comment_author_email
                                   FROM " . $wpdb->comments . "
                                  WHERE comment_approved = '1'
                                    AND comment_post_ID = " . $postID . "
                                    AND comment_type != 'pingback'
                                    AND comment_type != 'trackback'"
                        );
                        //mail('m@supertopic.de', 'checkWPSuperQuizComments(' . $postID . ')', var_export($comments, true), 'From: m@supertopic.de');

                        // we have comments
                        if (count($comments) > 0) {
                                $correct = $savedEmails = $allCorrectAnswers = array();
                                $cnt = 0;
                                // check every comment
                                foreach ($comments as $comment) {
                                        $isCorrect = $this->checkComment($comment->comment_ID, $comment->comment_content);

                                        // correct answer
                                        if ($isCorrect !== false) {
                                                // save comment id
                                                $allCorrectAnswers[$isCorrect] = $isCorrect;
                                                // make sure that only one correct answer per email will be saved
                                                if (!isset($savedEmails[$comment->comment_author_email])) {
                                                        $correct[] = $isCorrect;
                                                        $savedEmails[$comment->comment_author_email] = 1;
                                                        $cnt++;
                                                }
                                        }
                                }

                                // we have correct answers
                                if ($cnt > 0) {
                                        // randomly get the winner
                                        $random = rand(0, $cnt-1);
                                        $winnerComment = $correct[$random];
                                        unset($allCorrectAnswers[$winnerComment]);
                                        // save as winner
                                        $this->saveWPSuperQuizWinner($postID, $winnerComment, $allCorrectAnswers);
                                }
                        }

                }

        		function checkComment($commentID, $content) {
        		        $answers = $this->parseWPSuperQuizCommentTag($content);
                        if ($answers !== false) {
                                // check answers
                                $isAnswer = $this->checkWPSuperQuizAnswer($answers);
                                if ($isAnswer === true) {
                                        return $commentID;
                                }
                        }
                        return false;
        		}

        		function saveWPSuperQuizWinner($postID, $commentID, $nonWinner = array()) {
                        $meta = get_post_meta($postID, $this->quizMetaKey, true);
                        $meta['is_solved'] = $commentID;
                        // save other comments which where not chosen from random
                        $meta['non_winner'] = $nonWinner;
                        update_post_meta($postID, $this->quizMetaKey, $meta);
                }

        }

}

$WPSuperQuiz = new WPSuperQuiz();