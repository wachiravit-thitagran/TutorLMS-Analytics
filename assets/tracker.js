/**
 * TutorLMS Analytics Frontend Tracker
 */

(function() {
	if (typeof TutorLMSAnalyticsData === 'undefined') return;

	const API_URL = TutorLMSAnalyticsData.rest_url;
	const NONCE = TutorLMSAnalyticsData.nonce;
	
	// Determine current context (Course vs Lesson)
	let courseId = TutorLMSAnalyticsData.course_id;
	let lessonId = 0;

	// In Tutor LMS, if it's a lesson, course_id might be different or available in DOM
	const lessonWrap = document.querySelector('.tutor-single-lesson-wrap');
	if (lessonWrap) {
		const cId = lessonWrap.getAttribute('data-course-id');
		const lId = lessonWrap.getAttribute('data-lesson-id');
		if (cId) courseId = parseInt(cId, 10);
		if (lId) lessonId = parseInt(lId, 10);
	}

	function sendEvent(eventType, eventValue = '') {
		fetch(API_URL, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': NONCE
			},
			body: JSON.stringify({
				event_type: eventType,
				event_value: eventValue,
				course_id: courseId,
				lesson_id: lessonId
			})
		}).catch(console.error);
	}

	// 1. Track Page View
	sendEvent('page_view', window.location.href);

	// 2. Track Video Watch Time (Heartbeat)
	let watchTime = 0;
	let videoInterval = null;
	const videoElements = document.querySelectorAll('video, iframe'); // basic check, deeper integration with YouTube/Vimeo APIs is needed for true precision

	// Simplified heuristic: If user stays on a lesson page that contains an iframe/video, pulse every 30 seconds
	if (lessonId > 0 && videoElements.length > 0) {
		videoInterval = setInterval(() => {
			watchTime += 30;
			sendEvent('video_watch_heartbeat', watchTime.toString());
		}, 30000);
	}

	// 3. Track Lesson Exit (Time spent)
	let entryTime = Date.now();
	window.addEventListener('beforeunload', () => {
		let timeSpent = Math.floor((Date.now() - entryTime) / 1000);
		// Use beacon for reliable exit tracking
		navigator.sendBeacon(API_URL, JSON.stringify({
			event_type: 'page_exit',
			event_value: timeSpent.toString(),
			course_id: courseId,
			lesson_id: lessonId
		}));
	});

})();
