<?php

defined('MOODLE_INTERNAL') || die();

// general
$string['modulename'] = 'Vocabulary cards';
$string['modulenameplural'] = 'Vocabulary cards';
$string['pluginname'] = 'Vocabulary cards';
$string['modulename_help'] = 'Allows vocabulary cards to be created (from a vocabulary syllabus) and assigned to students within groups.';
$string['vocabcardsname'] = 'Vocabulary cards name';
$string['invalidvocabcardsid'] = 'Vocabulary cards ID was incorrect';
$string['invalidwordid'] = 'Word ID was incorrect';
$string['invalidcardid'] = 'Card ID was incorrect';
$string['invalidfeedbackid'] = 'Feedback ID was incorrect';
$string['creator'] = 'Creator';
$string['created'] = 'Created';
$string['modified'] = 'Modified';
$string['cards'] = 'Cards';
$string['noviewcapability'] = 'You cannot view this vocabulary cards activity';
$string['pluginadministration'] = 'Vocabulary cards administration';
$string['syllabus'] = 'Vocabulary syllabus';
$string['assignment'] = 'Vocabulary assignment';
$string['page'] = 'Page';
$string['of'] = 'Of';
$string['in'] = 'In';
$string['you'] = 'You';
$string['header'] = 'Header';
$string['footer'] = 'Footer';
$string['addnewword'] = 'Add new word';
$string['listexistingwords'] = 'List of existing words';
$string['listenrolledstudents'] = 'List of enrolled students';
$string['word'] = 'Word';
$string['card'] = 'Card';
$string['cards'] = 'Cards';
$string['status'] = 'Status';
$string['nowords'] = 'There are no words to show';
$string['nocards'] = 'There are no cards to show';
$string['section'] = 'Section';
$string['student'] = 'Student';
$string['nostudents'] = 'There are no students to show';
$string['wordaddedsuccessfully'] = 'The word "{$a}" was successfully added to the vocabulary syllabus (for this course)';
$string['wordupdatedsuccessfully'] = 'The word "{$a}" was successfully updated in the vocabulary syllabus (for this course)';
$string['cardcreatedsuccessfully'] = 'The card was created successfully';
$string['cardscreatedsuccessfully'] = '{$a} cards were created successfully';
$string['cardupdatedsuccessfully'] = 'The card was updated successfully';
$string['feedbackaddedsuccessfully'] = 'Feedback was successfully added to the card';
$string['feedbackupdatedsuccessfully'] = 'The feedback was updated successfully';
$string['startdate'] = 'Start date';
$string['startdate_help'] = 'Used only for determining when various notifications should be sent.';
$string['tutorgroups'] = 'Tutor groups / students';
$string['assign'] = 'Assign';
$string['selectoneormorewords'] = 'Select one or more words.';
$string['selectonegroup'] = 'Select one group (optional).';
$string['selectonestudent'] = 'Select one student.';
$string['backtolist'] = 'Back to list';
$string['backtolibrary'] = 'Back to library';
$string['examplesentences'] = 'Example sentences';
$string['feedback'] = 'Feedback';
$string['feedbackreceived'] = 'Feedback received';
$string['sendtostudent'] = 'Send to student';
$string['sendtotutor'] = 'Send to tutor';
$string['sendtorepository'] = 'Send to library';
$string['repository'] = 'Card library';
$string['timeaddedtorepo'] = 'Date added';
$string['owner'] = 'Owner';
$string['viewall'] = 'All';
$string['viewmine'] = 'My cards only';

// statuses (see vocabcard_status)
$string['status:0'] = 'Not started';
$string['status:1'] = 'In progress';
$string['status:2'] = 'In review';
$string['status:3'] = 'In library';

// capabilities
$string['vocabcards:addinstance'] = 'Add a new vocabulary cards activity';
$string['vocabcards:view'] = 'View a vocabulary cards activity';
$string['vocabcards:syllabus'] = 'Manage vocabulary syllabus';
$string['vocabcards:assignment'] = 'Assign vocabulary syllabus';
$string['vocabcards:feedback'] = 'Provide feedback on vocabulary cards';
$string['vocabcards:repository'] = 'View the card library';
$string['vocabcards:omniscience'] = 'View cards of any status in the card library';

// exceptions
$string['exception:ajax_only'] = 'AJAX requests only';
$string['exception:non_existent_partial'] = 'Non-existent partial';
$string['exception:word_already_exists'] = 'The word "{$a}" already exists in the vocabulary syllabus (for this course)';
$string['exception:word_already_assigned'] = 'The word "{$a}" has already been used to create at least one card (in this course)';
$string['exception:no_cardid'] = 'No card id was specified';

// JavaScript
$string['js:confirm_delete_word'] = 'Are you sure you want to delete this word?';
$string['js:confirm_delete_card'] = 'Are you sure you want to delete this card?';
$string['js:confirm_delete_feedback'] = 'Are you sure you want to delete this feedback?';
$string['js:confirm_assign_cards_without_group'] = 'Are you sure you want to assign words without choosing a group?';
$string['js:cannot_edit_card_as_in_review'] = 'This card is being reviewed by a tutor and cannot be edited.';
$string['js:cannot_edit_card_as_not_owner'] = 'You can only edit your own cards.';
$string['js:cannot_review_card_as_not_in_review'] = 'This card is not in review.';
$string['js:cannot_review_card_as_not_in_repository'] = 'This card is not in the card library.';
$string['js:cannot_view_card_as_not_in_repository'] = 'This card is not in the card library.';
$string['js:cannot_review'] = 'You cannot review cards.';
$string['js:cannot_review_own_card'] = 'You cannot review your own card.';
$string['js:menu_edit_cards'] = 'Edit cards';
$string['js:menu_review_cards'] = 'Review cards';
$string['js:word_deleted_successfully'] = 'The word was successfully deleted from the vocabulary syllabus (for this course)';
$string['js:card_deleted_successfully'] = 'The card was deleted successfully';
$string['js:feedback_deleted_successfully'] = 'The feedback was deleted successfully';
$string['js:omniscience'] = 'You have the capability to view cards of all statuses (not started, in progress, in review, in library)';

// labels
$string['label:definition'] = 'Definition';
$string['label:noun'] = 'Noun (n)';
$string['label:verb'] = 'Verb (v)';
$string['label:adverb'] = 'Adverb (adv)';
$string['label:adjective'] = 'Adjective (adj)';
$string['label:synonym_'] = 'Synonyms (syn)';
$string['label:antonym_'] = 'Antonyms (ant)';
$string['label:prefixes'] = 'Prefixes';
$string['label:suffixes'] = 'Suffixes';
$string['label:collocations'] = 'Collocations';
$string['label:tags'] = 'Tags';

// placeholders
$string['placeholder:searchbyword'] = 'Search by word';
$string['placeholder:searchbywordortag'] = 'Search by word or tag';
$string['placeholder:phonemic'] = 'Paste phonemic script here';

// notifications
$string['notify:vocabcard_assigned'] = 'You have been assigned a vocabulary card with the word "{$a->word}". Click <a href="{$a->url}">here</a> to start work on it.';
$string['notify:feedback_given_by_tutor'] = 'The course tutor has left feedback on your vocabulary card. Click <a href="{$a->url}">here</a> to see it.';
$string['notify:feedback_given_in_repository'] = 'Feedback has been left on your vocabulary card. Click <a href="{$a->url}">here</a> to see it.';
$string['notify:vocabcards_to_review_one'] = 'There is one vocabulary card to review in activity "{$a->name}". Click <a href="{$a->url}">here</a> to review it.';
$string['notify:vocabcards_to_review_many'] = 'There are {$a->count} vocabulary cards to review in activity "{$a->name}". Click <a href="{$a->url}">here</a> to review them.';
$string['notify:vocabcards_to_assign_one'] = 'There is one word to assign in course "{$a->name}". Click <a href="{$a->url}">here</a> to assign it.';
$string['notify:vocabcards_to_assign_many'] = 'There are {$a->count} words to assign in course "{$a->name}". Click <a href="{$a->url}">here</a> to assign them.';

// pdf
$string['pdf:title'] = 'Vocabulary card repository export';
$string['pdf:export_library'] = 'Export library';

// cron
$string['cron_start'] = 'Cron started.';
$string['cron_finish'] = 'Cron finished. {$a->okays} notifications successfully sent. {$a->fails} failed.';
$string['cron_finish_short'] = 'Cron finished.';
$string['cron_not_due'] = 'Cron not currently due at hour {$a->hour} to run within window {$a->window_after_hour} to {$a->window_before_hour}';
$string['cron_not_due_short'] = 'Cron not due.';
