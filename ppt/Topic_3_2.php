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

	<meta name='description' content='Topic 3 – THE ONLINE ENVIRONMENT'>
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
					<h4>Topic 3</h4>
					<h2>THE ONLINE ENVIRONMENT</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h2>SECTION 2 – FEATURES OF THE WORLD WIDE WEB</h2>
</section>
<section>
  <section><h4><b>11) </b>Define the term hypertext. [1]</h4></section>
  <section><p>Hypertext is text that contains links (hyperlinks) to other web pages, documents or media.</p></section>
</section>

<section>
  <section><h4><b>12) </b>Explain how hypertext improves navigation. [2]</h4></section>
  <section>
    <p>• Hypertext allows users to click links to move quickly between web pages or sections</p>
    <p>• This makes it easier and faster to find related information without manually searching</p>
  </section>
</section>

<section>
  <section><h4><b>13) </b>Describe what is meant by cross-platform. [2]</h4></section>
  <section>
    <p>• Cross-platform means the web can be accessed on different types of devices and operating systems</p>
    <p>• For example, it can be used on computers, smartphones, tablets, and smart TVs</p>
  </section>
</section>

<section>
  <section><h4><b>14) </b>Explain what is meant by the web being distributed. [2]</h4></section>
  <section>
    <p>• The web is distributed because it is stored across many servers in different locations worldwide</p>
    <p>• This reduces network load and improves reliability, as no single machine stores all data</p>
  </section>
</section>

<section>
  <section><h4><b>15) </b>Define open standards. [2]</h4></section>
  <section>
    <p>• Open standards are publicly available rules or guidelines that technologies must follow</p>
    <p>• They allow different systems and devices to communicate and work together effectively</p>
  </section>
</section>

<section>
  <section><h4><b>16) </b>Explain why open standards are important. [2]</h4></section>
  <section>
    <p>• They ensure compatibility between different devices and software</p>
    <p>• They allow any developer to create systems that can access and use the web</p>
  </section>
</section>

<section>
  <section><h4><b>17) </b>Explain why the web is described as dynamic. [2]</h4></section>
  <section>
    <p>• The web is dynamic because content is frequently updated and changed</p>
    <p>• Web pages can also display different content depending on user input or data</p>
  </section>
</section>

<section>
  <section><h4><b>18) </b>Describe the difference between Web 1.0 and Web 2.0. [4]</h4></section>
  <section>
    <table border="1" cellpadding="5">
      <tr>
        <th>Web 1.0</th>
        <th>Web 2.0</th>
      </tr>
      <tr>
        <td>Read-only content</td>
        <td>Interactive and participative</td>
      </tr>
      <tr>
        <td>Content created by few people</td>
        <td>User-generated content</td>
      </tr>
      <tr>
        <td>Limited user interaction</td>
        <td>Social media and collaboration tools</td>
      </tr>
      <tr>
        <td>Users only view content</td>
        <td>Users create, share, and modify content</td>
      </tr>
    </table>
  </section>
</section>

<section>
  <section><h4><b>19) </b>Explain the impact of Web 2.0 on users. [3]</h4></section>
  <section>
    <p>• Users can create and share content easily (e.g. social media, blogs)</p>
    <p>• It allows greater communication and collaboration between users worldwide</p>
    <p>• Users have more control and interaction, but also face risks such as privacy and misinformation</p>
  </section>
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

