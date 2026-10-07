<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Topic 2 – Networking'>
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
					<h4>Topic 2</h4>
					<h2> Networking</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

	  <section>
  <h2>SECTION 3 – EMAIL</h2>
</section>

<section>
  <section><h4><b>12) </b>Define email. [2]</h4></section>
  <section><p>Email (electronic mail) is a method of sending and receiving digital messages over a network such as the Internet between users using electronic devices.</p></section>
</section>

<section>
  <section><h4><b>13) </b>Identify two advantages of email. [2]</h4></section>
  <section><p>Fast communication, Messages can be sent instantly to people anywhere in the world.</p></section>
  <section><p>Provides a written record, Files and documents can be attached and sent along with the message.</p></section>
</section>

<section>
  <section><h4><b>14) </b>Identify two disadvantages of email. [2]</h4></section>
  <section><p>Emails can be ignored or overlooked, especially if the recipient receives many messages.</p></section>
  <section><p>Spam and phishing emails can cause security risks and waste time.</p></section>
</section>

<section>
  <section><h4><b>15) </b>Explain the purpose of CC and BCC. [3]</h4></section>
  <section><p>CC (Carbon Copy) is used to send a copy of an email to additional recipients so they are informed, and all recipients can see who else received the email.</p></section>
  <section><p>BCC (Blind Carbon Copy) is used to send a copy of an email without other recipients knowing, as BCC addresses are hidden, which helps maintain privacy.</p></section>
  <section><p>This protects privacy and prevents misuse of email addresses.</p></section>
</section>

<section>
  <section><h4><b>16) </b>Explain how email attachments are used. [3]</h4></section>
  <section><p>Email attachments are used to send files along with an email message, such as documents, images, spreadsheets, or presentations. The sender adds the file to the email, and the recipient can download and open the file on their device. This allows users to share information and work files quickly and easily without using physical storage devices.</p></section>
   
</section>

<section>
  <section><h4><b>17) </b>Explain security risks associated with email. [4]</h4></section>
  <section><p>Email has several security risks. Emails can contain malware or viruses in attachments or links, which can harm a computer or network when opened. Phishing emails may trick users into revealing personal information such as passwords or bank details. Emails can also be intercepted or accessed by unauthorised users if they are not encrypted. In addition, spam emails can waste time and may include harmful content or scams.</p></section>
   
</section>

<section>
  <section><h4><b>18) </b>Explain how spam emails affect organisations. [3]</h4></section>
  <section><p>Spam emails affect organisations by wasting employees’ time, as staff must spend time reading, deleting, or reporting unwanted messages. They can also reduce productivity and efficiency because attention is distracted from important work. In addition, spam emails may contain malicious links or attachments, increasing the risk of security breaches and data loss.</p></section>
</section>

<section>
  <section><h4><b>19) </b>Explain why email is suitable for formal communication. [3]</h4></section>
  <section><p>Email is suitable for formal communication because it allows messages to be written in a clear and professional format using proper language and structure. Emails also provide a written record that can be saved, referenced, or used as evidence later. In addition, email makes it easy to send official documents or attachments quickly to the intended recipients.</p></section>
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

