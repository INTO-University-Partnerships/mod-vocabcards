<?php

use Symfony\Component\HttpFoundation\Request;

defined('MOODLE_INTERNAL') || die();

$controller = $app['controllers_factory'];

// get words
$controller->get('/{courseid}/words', function (Request $request, $courseid) use ($app) {
    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    $caps = array_map(function ($cap) use ($app, $course) {
        $has_cap = $app['has_capability']('mod/vocabcards:' . $cap, context_course::instance($course->id));
        return $has_cap;
    }, array('syllabus', 'assignment'));
    if (!_or($caps)) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }
    unset($caps);

    // extract parameters from request
    $sectionid = (integer)$request->get('sectionid');
    $q = (string)$request->get('q');
    $limitfrom = (integer)$request->get('limitfrom');
    $limitnum = (integer)$request->get('limitnum');

    // get page of words (for the course) from the database given pagination parameters
    require_once __DIR__ . '/../models/vocabcards_syllabus_model.php';
    $model = new vocabcards_syllabus_model();
    $words = $model->get_all_by_courseid($courseid, $sectionid, $q, $limitfrom, $limitnum);

    // find the section name of each word
    $format = course_get_format($course);
    $words = array_map(function ($word) use ($format) {
        $word['sectionname'] = $format->get_section_name($word['sectionnum']);
        return $word;
    }, $words);

    // get total word count (for the course)
    $total = $model->get_total_by_courseid($courseid, $sectionid, $q);

    // return json response
    return $app->json((object)array(
        'words' => $words,
        'total' => $total,
    ));
})
->bind('getwords')
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+');

// get one word
$controller->get('/{courseid}/words/{wordid}', function ($courseid, $wordid) use ($app) {
    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    $caps = array_map(function ($cap) use ($app, $course) {
        $has_cap = $app['has_capability']('mod/vocabcards:' . $cap, context_course::instance($course->id));
        return $has_cap;
    }, array('syllabus', 'assignment'));
    if (!_or($caps)) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }
    unset($caps);

    // get word and return json response
    require_once __DIR__ . '/../models/vocabcards_syllabus_model.php';
    $model = new vocabcards_syllabus_model();
    try {
        $word = $model->get($courseid, $wordid);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidwordid', $app['plugin'])), 404);
    }
    return $app->json($word);
})
->bind('getword')
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+')
->assert('wordid', '\d+');

// post one word
$controller->post('/{courseid}/words', function (Request $request, $courseid) use ($app) {
    global $CFG, $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:syllabus', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // get data from request body
    $data = (array)json_decode($request->getContent());
    $data['courseid'] = $courseid;
    $data['creatorid'] = $USER->id;

    // save word
    require_once __DIR__ . '/../models/vocabcards_syllabus_model.php';
    $model = new vocabcards_syllabus_model();
    try {
        $data = $model->save($data, $app['now']());
    } catch (invalid_parameter_exception $e) {
        return $app->json(array('errorMessage' => $e->debuginfo), 400);
    }

    // get url of resource
    $url = $CFG->wwwroot . SLUG . $app['url_generator']->generate('getword', array(
        'courseid' => $courseid,
        'wordid' => $data['id'],
    ));

    // set message
    $data['successMessage'] = get_string('wordaddedsuccessfully', $app['plugin'], $data['word']);

    // return JSON response
    return $app->json($data, 201, array(
        'Location' => $url,
    ));
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+');

// put one word
$controller->put('/{courseid}/words/{wordid}', function (Request $request, $courseid, $wordid) use ($app) {
    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:syllabus', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // get data from request body
    $data = (array)json_decode($request->getContent());
    $data['id'] = $wordid;
    $data['courseid'] = $courseid;

    // save word
    require_once __DIR__ . '/../models/vocabcards_syllabus_model.php';
    $model = new vocabcards_syllabus_model();
    try {
        $model->get($courseid, $wordid);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidwordid', $app['plugin'])), 404);
    }
    try {
        $data = $model->save($data, $app['now']());
    } catch (invalid_parameter_exception $e) {
        return $app->json(array('errorMessage' => $e->debuginfo), 400);
    }

    // set message
    $data['successMessage'] = get_string('wordupdatedsuccessfully', $app['plugin'], $data['word']);

    // return JSON response
    return $app->json($data, 200);
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+')
->assert('wordid', '\d+');

// delete one word
$controller->delete('/{courseid}/words/{wordid}', function ($courseid, $wordid) use ($app) {
    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:syllabus', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // delete word and return json response
    require_once __DIR__ . '/../models/vocabcards_syllabus_model.php';
    $model = new vocabcards_syllabus_model();
    try {
        $model->delete($courseid, $wordid);
    } catch (invalid_parameter_exception $e) {
        return $app->json(array('errorMessage' => $e->debuginfo), 400);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidwordid', $app['plugin'])), 404);
    }
    return $app->json('', 204);
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+')
->assert('wordid', '\d+');

// get students
$controller->get('/{courseid}/students', function (Request $request, $courseid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:assignment', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // extract parameters from request
    $groupid = (integer)$request->get('groupid');
    $limitfrom = (integer)$request->get('limitfrom');
    $limitnum = (integer)$request->get('limitnum');

    // get page of students (for the course) from the database given pagination parameters
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_student_model.php';
    $model = new vocabcards_student_model();
    $students = $model->get_students_in_course($courseid, $USER->id, $groups, $groupid, $limitfrom, $limitnum);
    $total = $model->get_student_total_by_courseid($courseid, $groups, $groupid);

    // for each student, get a collection of cards
    if (!empty($students)) {
        require_once __DIR__ . '/../models/vocabcards_card_model.php';
        $model = new vocabcards_card_model();
        foreach ($students as $key => $student) {
            $students[$key]['cards'] = $model->get_student_cards_in_group($groupid, $student['id']);
        }
    }

    // return json response
    return $app->json((object)array(
        'students' => $students,
        'total' => $total,
    ));
})
->bind('getstudents')
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+');

// get cards
$controller->get('/{courseid}/cards', function (Request $request, $courseid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:view', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // extract parameters from request
    $instanceid = (integer)$request->get('instanceid');
    $limitfrom = (integer)$request->get('limitfrom');
    $limitnum = (integer)$request->get('limitnum');

    // get the cards
    require_once __DIR__ . '/../models/vocabcards_card_model.php';
    $model = new vocabcards_card_model();
    $cards = $model->get_student_cards_in_activity($instanceid, $USER->id, $limitfrom, $limitnum);
    $total = $model->get_total_student_cards_in_activity($instanceid, $USER->id);

    // return json response
    return $app->json((object)array(
        'cards' => $cards,
        'total' => $total,
    ));
})
->bind('getcards')
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+');

// get cards in review
$controller->get('/{courseid}/cards/review', function (Request $request, $courseid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:feedback', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // extract parameters from request
    $instanceid = (integer)$request->get('instanceid');
    $limitfrom = (integer)$request->get('limitfrom');
    $limitnum = (integer)$request->get('limitnum');

    // get the cards
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_card_model.php';
    $model = new vocabcards_card_model();
    $cards = $model->get_cards_in_review_in_activity($instanceid, $groups, $limitfrom, $limitnum);
    $total = $model->get_total_cards_in_review_in_activity($instanceid, $groups);

    // return json response
    return $app->json((object)array(
        'cards' => $cards,
        'total' => $total,
    ));
})
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+');

// get cards in repository
$controller->get('/{courseid}/cards/repository', function (Request $request, $courseid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:repository', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // extract parameters from request
    $groupid = (integer)$request->get('groupid');
    $userid = (integer)$request->get('userid');
    $q = (string)$request->get('q');
    $status = (integer)$request->get('status');
    $sort = (string)$request->get('sort');
    $limitfrom = (integer)$request->get('limitfrom');
    $limitnum = (integer)$request->get('limitnum');

    // get the cards
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_card_model.php';
    $model = new vocabcards_card_model();
    $cards = $model->get_cards_in_repository_in_course($courseid, $groups, $groupid, $userid, $q, $status, $sort, $limitfrom, $limitnum);
    $total = $model->get_total_cards_in_repository_in_course($courseid, $groups, $groupid, $userid, $q, $status);

    // return json response
    return $app->json((object)array(
        'cards' => $cards,
        'total' => $total,
    ));
})
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+');

// get one card
$controller->get('/{courseid}/cards/{cardid}', function ($courseid, $cardid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:view', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // get word and return json response
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_card_model.php';
    $model = new vocabcards_card_model();
    try {
        $card = $model->get($cardid, $groups);
        $card['feedback_count'] = $DB->count_records('vocabcards_feedback', array('cardid' => $cardid));
        $card['feedback_modified'] = $model->get_feedback_modified($cardid);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidcardid', $app['plugin'])), 404);
    }
    return $app->json($card);
})
->bind('getcard')
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+')
->assert('cardid', '\d+');

// create cards (plural)
$controller->post('/{courseid}/cards/create', function (Request $request, $courseid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:assignment', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // get data from request body
    $data = (array)json_decode($request->getContent());

    // create cards
    require_once __DIR__ . '/../models/vocabcards_card_model.php';
    $cards = array();
    $model = new vocabcards_card_model();
    $wordids = $data['wordids'];
    foreach ($wordids as $wordid) {
        $card = $model->create_from_word($wordid, $data['ownerid'], $data['groupid'], $USER->id, $app['now']());
        $cards[] = $card;

        // trigger event to notify the student that a card has been assigned
        $app['trigger_event']('vocabcard_assigned', array(
            'context' => context_course::instance($courseid),
            'objectid' => $card['id'],
            'relateduserid' => $card['ownerid'],
            'other' => array(
                'wordid' => $card['wordid'],
                'cmid' => $model->get_cmid_from_cardid($card['id']),
            )
        ));
    }

    // return JSON response
    return $app->json(array(
        'cards' => $cards,
        'successMessage' => get_string(count($cards) > 1 ? 'cardscreatedsuccessfully' : 'cardcreatedsuccessfully', $app['plugin'], count($cards)),
    ), 201);
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+');

// put one card
$controller->put('/{courseid}/cards/{cardid}', function (Request $request, $courseid, $cardid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:view', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // get data from request body
    $data = (array)json_decode($request->getContent());

    // ownership check
    if ($USER->id != $data['ownerid']) {
        if (!$app['has_capability']('mod/vocabcards:assignment', context_course::instance($course->id))) {
            return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
        }
    }

    // save card
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_card_model.php';
    $model = new vocabcards_card_model();
    try {
        $model->get($cardid, $groups);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidcardid', $app['plugin'])), 404);
    }
    try {
        $data = $model->save($data, $app['now']());
    } catch (invalid_parameter_exception $e) {
        return $app->json(array('errorMessage' => $e->debuginfo), 400);
    }

    // set message
    $data['successMessage'] = get_string('cardupdatedsuccessfully', $app['plugin']);

    // return JSON response
    return $app->json($data, 200);
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+');

// delete one card
$controller->delete('/{courseid}/cards/{cardid}', function ($courseid, $cardid) use ($app) {
    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:assignment', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // delete card and return json response
    require_once __DIR__ . '/../models/vocabcards_card_model.php';
    $model = new vocabcards_card_model();
    try {
        $model->delete($cardid);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidcardid', $app['plugin'])), 404);
    }
    return $app->json('', 204);
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+')
->assert('cardid', '\d+');

// get all feedback left against a particular card
$controller->get('/{courseid}/feedback', function (Request $request, $courseid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:view', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // extract parameters from request
    $cardid = (integer)$request->get('cardid');
    $limitfrom = (integer)$request->get('limitfrom');
    $limitnum = (integer)$request->get('limitnum');

    // get page of feedback (for a card) from the database given pagination parameters
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_feedback_model.php';
    $model = new vocabcards_feedback_model();
    $feedback = $model->get_all_by_cardid($cardid, $groups, $limitfrom, $limitnum);

    // find whether each user who left feedback is a tutor
    $feedback = array_map(function ($feedback) use ($app, $courseid) {
        $feedback['is_tutor'] = $app['has_capability']('mod/vocabcards:feedback', context_course::instance($courseid), $feedback['userid']);
        return $feedback;
    }, $feedback);

    // get total feedback count (for the card)
    $total = $model->get_total_by_cardid($cardid, $groups);

    // return json response
    return $app->json((object)array(
        'feedback' => $feedback,
        'total' => $total,
    ));
})
->bind('getfeedbacks')
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+');

// get one (piece of) feedback
$controller->get('/{courseid}/feedback/{feedbackid}', function ($courseid, $feedbackid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if (!$app['has_capability']('mod/vocabcards:view', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // get feedback and return json response
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_feedback_model.php';
    $model = new vocabcards_feedback_model();
    try {
        $feedback = $model->get($feedbackid, $groups);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidfeedbackid', $app['plugin'])), 404);
    }
    return $app->json($feedback);
})
->bind('getfeedback')
->before($app['middleware']['ajax_request'])
->assert('courseid', '\d+')
->assert('feedbackid', '\d+');

// post one (piece of) feedback
$controller->post('/{courseid}/feedback', function (Request $request, $courseid) use ($app) {
    global $CFG, $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if ($request->get('app') != 'repository' && !$app['has_capability']('mod/vocabcards:feedback', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // get data from request body
    $data = (array)json_decode($request->getContent());
    $data['userid'] = $USER->id;

    // save feedback
    require_once __DIR__ . '/../models/vocabcards_feedback_model.php';
    $model = new vocabcards_feedback_model();
    $data = $model->save($data, $app['now']());

    // get url of resource
    $url = $CFG->wwwroot . SLUG . $app['url_generator']->generate('getfeedback', array(
        'courseid' => $courseid,
        'feedbackid' => $data['id'],
    ));

    // set message
    $data['successMessage'] = get_string('feedbackaddedsuccessfully', $app['plugin']);

    // determine event name based on where the feedback was left
    $eventName = 'feedback_given_' . ($request->get('app') == 'repository' ? 'in_repository' : 'by_tutor');

    // trigger event to notify user of feedback that has been left
    $app['trigger_event']($eventName, array(
        'context' => context_course::instance($courseid),
        'objectid' => $data['cardid'],
        'relateduserid' => (integer)$DB->get_field('vocabcards_card', 'ownerid', array(
            'id' => $data['cardid'],
        )),
        'other' => array(
            'cmid' => (integer)$request->get('cmid'),
        ),
    ));

    // return JSON response
    return $app->json($data, 201, array(
        'Location' => $url,
    ));
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+');

// put one (piece of) feedback
$controller->put('/{courseid}/feedback/{feedbackid}', function (Request $request, $courseid, $feedbackid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if ($request->get('app') != 'repository' && !$app['has_capability']('mod/vocabcards:feedback', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // get data from request body
    $data = (array)json_decode($request->getContent());
    $data['id'] = $feedbackid;

    // save feedback
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_feedback_model.php';
    $model = new vocabcards_feedback_model();
    try {
        $feedback = $model->get($feedbackid, $groups);
        if (!$app['has_capability']('mod/vocabcards:feedback', context_course::instance($course->id)) && ($USER->id != $feedback['userid'])) {
            return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
        }
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidfeedbackid', $app['plugin'])), 404);
    }
    $data = $model->save($data, $app['now']());

    // set message
    $data['successMessage'] = get_string('feedbackupdatedsuccessfully', $app['plugin']);

    // return JSON response
    return $app->json($data, 200);
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+')
->assert('feedbackid', '\d+');

// delete one (piece of) feedback
$controller->delete('/{courseid}/feedback/{feedbackid}', function (Request $request, $courseid, $feedbackid) use ($app) {
    global $USER;

    /** @var moodle_database */
    global $DB;

    // courseid check
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        return $app->json(array('errorMessage' => get_string('invalidcourseid', 'error')), 404);
    }

    // require course login
    $app['require_course_login']($course, null, false);

    // capability check
    if ($request->get('app') != 'repository' && !$app['has_capability']('mod/vocabcards:feedback', context_course::instance($course->id))) {
        return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
    }

    // delete feedback and return json response
    $groups = $app['get_user_groups_in_course']($courseid, $USER->id);
    require_once __DIR__ . '/../models/vocabcards_feedback_model.php';
    $model = new vocabcards_feedback_model();
    try {
        $feedback = $model->get($feedbackid, $groups);
        if (!$app['has_capability']('mod/vocabcards:feedback', context_course::instance($course->id)) && ($USER->id != $feedback['userid'])) {
            return $app->json(array('errorMessage' => get_string('accessdenied', 'admin')), 403);
        }
        $model->delete($feedbackid);
    } catch (dml_missing_record_exception $e) {
        return $app->json(array('errorMessage' => get_string('invalidfeedbackid', $app['plugin'])), 404);
    }
    return $app->json('', 204);
})
->before($app['middleware']['ajax_request'])
->before($app['middleware']['ajax_sesskey'])
->assert('courseid', '\d+')
->assert('feedbackid', '\d+');

// return the controller
return $controller;
