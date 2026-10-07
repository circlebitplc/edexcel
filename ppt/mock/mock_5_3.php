<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Mock 5'>
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
					<h4>Mock 5</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
			 <!-- 3(a)(i) -->
    <section>
        <section>
            <h4><b>3(a)(i)</b> Two online services (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Email</p>
            <p>• Video conferencing</p>
			<p>• Email services
</p>
			<p>• Online banking
</p>
			<p>• E-commerce websites
</p>
			<p>• Cloud storage
</p>
			<p>• Video conferencing
</p>
			<p>• Social networking
</p>
			<p>• Online education
</p>
			<p>• Streaming services</p>
        </section>
    </section>

    <!-- 3(a)(ii) -->
    <section>
        <section>
            <h4><b>3(a)(ii)</b> Features of collaboration systems (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p> 
<table border="1" cellspacing="0" cellpadding="8" style="border-collapse: collapse; width: 100%;">

    <tr>
        <th>Feature</th>
        <th>Description</th>
    </tr>

    <tr>
        <td>Shared Documents</td>
        <td>Multiple users can access and edit the same file</td>
    </tr>

    <tr>
        <td>Real-Time Editing</td>
        <td>Changes made by one user appear instantly to others</td>
    </tr>

    <tr>
        <td>Communication Tools</td>
        <td>Includes chat, messaging, email, or video conferencing</td>
    </tr>

    <tr>
        <td>File Sharing</td>
        <td>Users can upload, download, and share files</td>
    </tr>

    <tr>
        <td>Version Control</td>
        <td>Tracks changes and stores previous versions of documents</td>
    </tr>

    <tr>
        <td>User Permissions</td>
        <td>Controls who can view, edit, or delete files</td>
    </tr>

    <tr>
        <td>Cloud Access</td>
        <td>Files and systems can be accessed from different locations</td>
    </tr>

    <tr>
        <td>Task Management</td>
        <td>Assigns tasks and tracks progress</td>
    </tr>

    <tr>
        <td>Comments and Feedback</td>
        <td>Users can leave notes or suggestions on work</td>
    </tr>

    <tr>
        <td>Synchronisation</td>
        <td>Updates are automatically synced between users</td>
    </tr>

</table> 
        </section>
    </section>

    <!-- 3(b) -->
    <section>
        <section>
            <h4><b>3(b)</b> Validation (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Checking data is sensible</p>
        </section>
    </section>

    <!-- 3(c) -->
    <section>
        <section>
            <h4><b>3(c)</b> Secure practice (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Using strong and unique passwords</p>
        </section>
    </section>

    <!-- 3(d) -->
    <section>
        <section>
            <h4><b>3(d)</b> Two ways systems improve response (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>1) Online systems allow emergency teams to communicate quickly using email, messaging, and video conferencing, helping organisations coordinate rescue operations efficiently.
			</p></section>
        <section><p>2) 
Real-time data can be collected and shared instantly, allowing authorities to monitor affected areas and make faster decisions during the crisis.</p></section>
        <section><p>3) 
GPS and online mapping systems help locate damaged areas, track emergency vehicles, and identify safe evacuation routes.
			</p></section>
        <section><p>4) 
Cloud-based systems enable workers in different locations to access the same information and update records immediately.
			</p></section>
        <section><p>5) 
Online warning systems can send alerts and emergency notifications to large numbers of people quickly through websites, apps, or SMS services.
			</p></section>
        <section><p>6) 
Collaboration systems allow multiple organisations such as hospitals, police, and rescue teams to work together by sharing files, reports, and resources online.
        </section>
    </section>

    <!-- 3(e)(i) -->
    <section>
        <section>
            <h4><b>3(e)(i)</b> Wiki vs blog (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A wiki allows multiple users to edit content.</p>
            <p>A blog is usually written by one author.</p>
        </section>
    </section>

    <!-- 3(e)(ii) -->
    <section>
        <section>
            <h4><b>3(e)(ii)</b> Acceptable use policy (2)</h4>
        </section>
        <section>
			</p>
		An acceptable use policy helps improve security because staff are given rules about safe use of online systems, reducing the risk of hacking, malware, or data breaches.
It explains what employees are allowed and not allowed to do online, helping prevent misuse of company systems such as accessing inappropriate websites or downloading illegal software.</p></section>
        <section><p>
The policy helps protect confidential data by instructing staff on correct procedures for handling passwords, emails, and sensitive information.
			</p></section>
        <section><p>
It reduces the chances of accidental damage to systems because employees understand the correct way to use hardware, software, and network resources.
			</p></section>
        <section><p>
An acceptable use policy can improve productivity by limiting non-work-related internet usage during working hours.
			</p></section>
        <section><p>
It provides legal protection for the organisation because staff are informed about rules and responsibilities before using the systems.
			</p></section>
        <section><p>
The policy helps ensure consistent behaviour among all employees when using online services and communication systems.
It can reduce cyberbullying, harassment, or inappropriate communication within the organisation by setting clear standards of behaviour online.
			</p>
		
    </section></section>

    <!-- 3(f) -->
    <section>
        <section>
            <h4><b>3(f)</b> Moderation methods (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Automatic filtering</p>
            <p>• Human moderation</p>
        </section>
    </section>
 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_5_4.php">next</a></p>
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

