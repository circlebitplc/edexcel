<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Chapter 8 – Online Communities'>
	<meta name='author' content='Enidu Batuwanthudawe'>

	<meta name='apple-mobile-web-app-capable' content='yes' />
	<meta name='apple-mobile-web-app-status-bar-style' content='black-translucent' />

	<meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>

	<link rel='stylesheet' href='<?= $pptBase ?>/css/reveal.min.css'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/theme/default.css' id='theme'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/custom.css'>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<!-- For syntax highlighting -->
	<link rel='stylesheet' href='<?= $pptBase ?>/lib/css/zenburn.css'>

	<!-- If the query includes 'print-pdf', use the PDF print sheet -->
	<?php if (isset($_GET['print-pdf'])): ?>
	<link rel="stylesheet" href="<?= $pptBase ?>/css/print/pdf.css">
	<?php endif; ?>

		<!--[if lt IE 9]>
		<script src='<?= $pptBase ?>/lib/js/html5shiv.js'></script>
		<![endif]-->
		<script src='<?= $pptBase ?>/js/moment.min.js'></script>
	</head>

	<body>

		<div class='reveal'>
			<div class='slides'>
				<section>
					<h4>Chapter 8</h4>
					<h2>Online Communities</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple-Choice Questions (MCQs)</h3>
</section>

<section>
  <section><h4><b>1) </b>An online community is:</h4></section>
  <section><p>Answer: B. A group with shared interests communicating online</p></section>
</section>

<section>
  <section><h4><b>2) </b>Social networking sites mainly allow users to:</h4></section>
  <section><p>Answer: B. Socialize and share content</p></section>
</section>

<section>
  <section><h4><b>3) </b>Online gaming communities include features such as:</h4></section>
  <section><p>Answer: B. Experience points and profiles</p></section>
</section>

<section>
  <section><h4><b>4) </b>A VLE (Virtual Learning Environment) allows:</h4></section>
  <section><p>Answer: C. Teachers and students to share learning materials</p></section>
</section>

<section>
  <section><h4><b>5) </b>A wiki is a site where:</h4></section>
  <section><p>Answer: C. Many users can add and edit content</p></section>
</section>

<section>
  <section><h4><b>6) </b>A forum thread is:</h4></section>
  <section><p>Answer: B. A series of messages on a topic</p></section>
</section>

<section>
  <section><h4><b>7) </b>Targeted marketing means:</h4></section>
  <section><p>Answer: B. Ads matched to user data and interests</p></section>
</section>

<section>
  <section><h4><b>8) </b>Moderators in a forum can:</h4></section>
  <section><p>Answer: B. Approve or block posts/users</p></section>
</section>

<section>
  <section><h4><b>9) </b>Social bookmarking allows users to:</h4></section>
  <section><p>Answer: A. Save and tag websites for others to find</p></section>
</section>

<section>
  <section><h4><b>10) </b>Cyberbullying should be:</h4></section>
  <section><p>Answer: C. Reported immediately</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define an online community.</h4></section>
  <section><p>An online community is a group of people who interact and communicate over the internet.</p></section>
  <section><p>The interaction is usually based on shared interests or goals.</p></section>
</section>

<section>
  <section><h4><b>12) </b>State two features of a social networking site.</h4></section>
  <section><p>User profiles.</p></section>
  <section><p>Content sharing such as posts, photos, and videos.</p></section>
</section>

<section>
  <section><h4><b>13) </b>What is the function of an online workspace?</h4></section>
  <section><p>An online workspace allows users to collaborate on tasks.</p></section>
  <section><p>It supports file sharing and communication in real time.</p></section>
</section>

<section>
  <section><h4><b>14) </b>Name two features of a VLE.</h4></section>
  <section><p>Assignment submission.</p></section>
  <section><p>Online learning resources.</p></section>
</section>

<section>
  <section><h4><b>15) </b>What is the purpose of a forum moderator?</h4></section>
  <section><p>A moderator manages user behaviour and forum content.</p></section>
  <section><p>They enforce rules and keep discussions safe and appropriate.</p></section>
</section>

<section>
  <section><h4><b>16) </b>Define targeted marketing.</h4></section>
  <section><p>Targeted marketing is advertising based on user data.</p></section>
  <section><p>This includes behaviour, interests, and online activity.</p></section>
</section>

<section>
  <section><h4><b>17) </b>What is analytics used for?</h4></section>
  <section><p>Analytics is used to track user behaviour and engagement.</p></section>
  <section><p>It helps improve content and platform performance.</p></section>
</section>

<section>
  <section><h4><b>18) </b>State one risk of anonymity online.</h4></section>
  <section><p>Users may behave abusively without fear of being identified.</p></section>
</section>

<section>
  <section><h4><b>19) </b>What is cyberbullying?</h4></section>
  <section><p>Cyberbullying is the use of digital technology to harass or threaten someone.</p></section>
</section>

<section>
  <section><h4><b>20) </b>Give one way users can stay safe when sharing content online.</h4></section>
  <section><p>Use privacy settings to control who can see shared content.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Explain the differences between wikis and forums.</h4></section>
  <section><p>Wikis allow users to collaboratively edit shared content.</p></section>
  <section><p>Forums are discussion-based and organised into threads.</p></section>
  <section><p>Wikis focus on building information, while forums focus on discussion.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Describe features of online gaming communities.</h4></section>
  <section><p>They include user profiles and rankings.</p></section>
  <section><p>Achievements and rewards encourage engagement.</p></section>
  <section><p>Chat features support communication between players.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Explain how targeted marketing works.</h4></section>
  <section><p>User data such as likes and browsing behaviour is collected.</p></section>
  <section><p>Ads are then displayed based on user interests.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Describe three ways moderators keep forums safe.</h4></section>
  <section><p>Removing offensive posts.</p></section>
  <section><p>Warning or banning users.</p></section>
  <section><p>Approving posts before publication.</p></section>
</section>

<section>
  <section><h4><b>25) </b>Explain social bookmarking sites.</h4></section>
  <section><p>They allow users to save and tag website links.</p></section>
  <section><p>This helps users organise and discover useful content.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Risks of geotagging locations.</h4></section>
  <section><p>Strangers may track the user’s movements.</p></section>
  <section><p>This increases the risk of stalking or physical harm.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Effect of incorrect wiki edits.</h4></section>
  <section><p>Incorrect information reduces reliability.</p></section>
  <section><p>Users may trust and share false information.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Actions moderators can take.</h4></section>
  <section><p>Delete offensive posts.</p></section>
  <section><p>Issue warnings.</p></section>
  <section><p>Permanently ban the user.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Benefits of VLEs for staff training.</h4></section>
  <section><p>Training materials are accessible anytime.</p></section>
  <section><p>Progress can be tracked efficiently.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Steps to take when cyberbullied.</h4></section>
  <section><p>Save evidence of messages.</p></section>
  <section><p>Block and report the bully.</p></section>
  <section><p>Inform a trusted adult or authority.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Comparison of online communities.</h4></section>
  <section><p>Social networks focus on sharing and communication.</p></section>
  <section><p>VLEs support education and training.</p></section>
  <section><p>Wikis build shared knowledge.</p></section>
  <section><p>Forums support discussion.</p></section>
  <section><p>Gaming communities focus on interaction and competition.</p></section>
  <section><p>Workspaces support professional collaboration.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Risks of online anonymity.</h4></section>
  <section><p>Anonymity can lead to cyberbullying and scams.</p></section>
  <section><p>Users should use privacy controls.</p></section>
  <section><p>Avoid sharing personal information.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Role of tagging, analytics, and integrations.</h4></section>
  <section><p>Tagging helps organise and find content.</p></section>
  <section><p>Analytics track user behaviour.</p></section>
  <section><p>Third-party integrations add extra features.</p></section>
</section>


 

			</div>
		</div>

		<script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
		<script src="<?= $pptBase ?>/js/reveal.min.js"></script>

		<script>

			// Full list of configuration options available here:
			// https://github.com/hakimel/reveal.js#configuration
			Reveal.initialize({
				controls: true,
				progress: true,
				history: true,
				center: true,

				theme: Reveal.getQueryHash().theme, // available themes are in /css/theme
				transition: Reveal.getQueryHash().transition || 'default', // default/cube/page/concave/zoom/linear/fade/none

				// Parallax scrolling
				// parallaxBackgroundImage: 'https://s3.amazonaws.com/hakim-static/reveal-js/reveal-parallax-1.jpg',
				// parallaxBackgroundSize: '2100px 900px',

				// Optional libraries used to extend on reveal.js
				dependencies: [
					{ src: '<?= $pptBase ?>/lib/js/classList.js', condition: function() { return !document.body.classList; } },
					{ src: '<?= $pptBase ?>/plugin/markdown/marked.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?= $pptBase ?>/plugin/markdown/markdown.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?= $pptBase ?>/plugin/highlight/highlight.js', async: true, callback: function() { hljs.initHighlightingOnLoad(); } },
					{ src: '<?= $pptBase ?>/plugin/zoom-js/zoom.js', async: true, condition: function() { return !!document.body.classList; } },
					
				]
			});

		</script>

	</body>
</html>

