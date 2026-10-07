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

	<meta name='description' content='Chapter 6 – Risks to Data & Information'>
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
					<h4>Chapter 6</h4>
					<h2>Risks to Data & Information</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple-Choice Questions (MCQs)</h3>
</section>

<section>
  <section><h4><b>1) </b>What is unauthorized access?</h4></section>
  <section><p>Answer: B. Gaining access without permission</p></section>
</section>

<section>
  <section><h4><b>2) </b>Malware is:</h4></section>
  <section><p>Answer: B. Software designed to cause harm</p></section>
</section>

<section>
  <section><h4><b>3) </b>Spyware is:</h4></section>
  <section><p>Answer: B. Software that steals personal data secretly</p></section>
</section>

<section>
  <section><h4><b>4) </b>Phishing involves:</h4></section>
  <section><p>Answer: A. Fake emails/websites tricking users</p></section>
</section>

<section>
  <section><h4><b>5) </b>Smishing is phishing through:</h4></section>
  <section><p>Answer: B. SMS</p></section>
</section>

<section>
  <section><h4><b>6) </b>Pharming redirects users to:</h4></section>
  <section><p>Answer: B. Fake websites</p></section>
</section>

<section>
  <section><h4><b>7) </b>Anti-virus software works by:</h4></section>
  <section><p>Answer: B. Checking files against virus definitions</p></section>
</section>

<section>
  <section><h4><b>8) </b>HTTPS ensures:</h4></section>
  <section><p>Answer: C. Secure communication</p></section>
</section>

<section>
  <section><h4><b>9) </b>A differential backup copies:</h4></section>
  <section><p>Answer: B. Files changed since last full backup</p></section>
</section>

<section>
  <section><h4><b>10) </b>CAPTCHA is used to:</h4></section>
  <section><p>Answer: A. Identify humans vs computers</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define malware.</h4></section>
  <section><p>Malware is malicious software designed to harm a computer system.</p></section>
  <section><p>It may disrupt operations, steal data, or gain unauthorised access without the user’s knowledge.</p></section>
</section>

<section>
  <section><h4><b>12) </b>What is a botnet?</h4></section>
  <section><p>A botnet is a network of infected computers.</p></section>
  <section><p>They are controlled remotely by a hacker to send spam, launch cyberattacks, or spread malware.</p></section>
</section>

<section>
  <section><h4><b>13) </b>Give two causes of accidental data deletion.</h4></section>
  <section><p>Human error such as deleting the wrong file.</p></section>
  <section><p>Software crashes or power failures during saving.</p></section>
</section>

<section>
  <section><h4><b>14) </b>What is spear phishing?</h4></section>
  <section><p>Spear phishing is a targeted phishing attack.</p></section>
  <section><p>Messages are personalised to a specific individual or organisation to appear more convincing.</p></section>
</section>

<section>
  <section><h4><b>15) </b>Explain pharming.</h4></section>
  <section><p>Pharming redirects users to fake websites without their knowledge.</p></section>
  <section><p>This can occur even when the correct web address is entered.</p></section>
  <section><p>It is used to steal login details and personal information.</p></section>
</section>

<section>
  <section><h4><b>16) </b>What is HTTPS used for?</h4></section>
  <section><p>HTTPS secures communication between a browser and a website.</p></section>
  <section><p>It encrypts data such as passwords and payment details.</p></section>
</section>

<section>
  <section><h4><b>17) </b>Define full backup.</h4></section>
  <section><p>A full backup is a complete copy of all files and data.</p></section>
  <section><p>It represents the system state at a specific point in time.</p></section>
</section>

<section>
  <section><h4><b>18) </b>What is a CSC (Card Security Code)?</h4></section>
  <section><p>A CSC is a security number printed on a bank card.</p></section>
  <section><p>It verifies that the person making an online payment physically owns the card.</p></section>
</section>

<section>
  <section><h4><b>19) </b>Why must anti-virus software be updated regularly?</h4></section>
  <section><p>New viruses and malware are created frequently.</p></section>
  <section><p>Updates allow the software to recognise and block new threats.</p></section>
</section>

<section>
  <section><h4><b>20) </b>What does SSL/TLS do?</h4></section>
  <section><p>SSL/TLS encrypts data sent between a web browser and a server.</p></section>
  <section><p>This prevents hackers from intercepting sensitive information.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Three risks to data when stored digitally.</h4></section>
  <section><p>Malware attacks can corrupt or delete data.</p></section>
  <section><p>Unauthorised access can result in data theft or modification.</p></section>
  <section><p>Hardware failure can cause permanent data loss if no backups exist.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Compare phishing, smishing, and spear phishing.</h4></section>
  <section><p>Phishing uses fake emails or websites sent to many users.</p></section>
  <section><p>Smishing is phishing carried out using SMS messages.</p></section>
  <section><p>Spear phishing is targeted and personalised, making it harder to detect.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Two methods criminals use to perform pharming.</h4></section>
  <section><p>DNS poisoning alters DNS records to redirect users.</p></section>
  <section><p>Malware infection changes host files to redirect web traffic.</p></section>
</section>

<section>
  <section><h4><b>24) </b>How anti-virus and anti-malware tools protect data.</h4></section>
  <section><p>They scan files using virus definitions.</p></section>
  <section><p>Malicious software is blocked, quarantined, or removed.</p></section>
  <section><p>Real-time protection prevents infections during downloads or access.</p></section>
</section>

<section>
  <section><h4><b>25) </b>Compare full, differential, and incremental backups.</h4></section>
  <section><p>A full backup copies all data.</p></section>
  <section><p>A differential backup copies data changed since the last full backup.</p></section>
  <section><p>An incremental backup copies data changed since the last backup of any type.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Fake email asking for bank details.</h4></section>
  <section><p>This is a phishing attack.</p></section>
  <section><p>Criminals pretend to be a trusted organisation.</p></section>
  <section><p>Users are tricked into entering sensitive data on fake websites.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Explain how botnets operate.</h4></section>
  <section><p>The infected laptop becomes part of a botnet.</p></section>
  <section><p>Hackers control it remotely using command-and-control servers.</p></section>
  <section><p>The botnet spreads malware or launches attacks.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Misspelled website URL risk.</h4></section>
  <section><p>The user may enter details into a fake website.</p></section>
  <section><p>This attack is known as pharming or URL spoofing.</p></section>
  <section><p>Its purpose is to steal login or financial data.</p></section>
</section>

<section>
  <section><h4><b>29) </b>How HTTPS protects online payments.</h4></section>
  <section><p>HTTPS encrypts data during transmission.</p></section>
  <section><p>This prevents interception of payment information.</p></section>
  <section><p>SSL/TLS also verifies website identity.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Recommended backup method for a school.</h4></section>
  <section><p>Daily incremental backups with weekly full backups are recommended.</p></section>
  <section><p>This saves storage space and reduces backup time.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Types of malware and their harm.</h4></section>
  <section><p>A virus attaches to files and damages data.</p></section>
  <section><p>A worm self-replicates across networks.</p></section>
  <section><p>A Trojan disguises itself as legitimate software.</p></section>
  <section><p>Adware displays unwanted advertisements.</p></section>
  <section><p>Spyware secretly records personal information.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Methods of protecting data.</h4></section>
  <section><p>Firewalls block unauthorised access.</p></section>
  <section><p>Encryption protects data confidentiality.</p></section>
  <section><p>Strong passwords prevent unauthorised logins.</p></section>
  <section><p>CAPTCHA blocks automated bots.</p></section>
  <section><p>Anti-malware removes threats.</p></section>
  <section><p>File permissions restrict access.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Backup strategies for a large company.</h4></section>
  <section><p>Automated schedules with daily incremental and weekly full backups.</p></section>
  <section><p>Cloud and local backups provide redundancy.</p></section>
  <section><p>Multiple copies are stored in different locations.</p></section>
  <section><p>Backups are encrypted and stored securely.</p></section>
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

