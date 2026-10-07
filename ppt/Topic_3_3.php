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
  <h2>SECTION 3– STATIC & DYNAMIC WEB PAGES</h2>
</section>
<section>
  <section><h4><b>20) </b>Define a static web page. [1]</h4></section>
  <section><p>A static web page is a web page where the content remains the same for all users unless manually updated by the author.</p></section>
</section>

<section>
  <section><h4><b>21) </b>Define a dynamic web page. [1]</h4></section>
  <section><p>A dynamic web page is a web page where the content is generated each time it is requested and can vary for different users.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Describe two characteristics of a static web page. [2]</h4></section>
  <section>
    <p>• The content does not change automatically and is the same for every user</p>
    <p>• It is usually written using HTML/CSS and requires minimal processing by the server</p>
  </section>
</section>

<section>
  <section><h4><b>23) </b>Describe two characteristics of a dynamic web page. [2]</h4></section>
  <section>
    <p>• The content can change based on user input or data from a database</p>
    <p>• It requires additional processing on the server before being sent to the browser</p>
  </section>
</section>

<section>
  <section><h4><b>24) </b>Explain the difference between static and dynamic web pages. [4]</h4></section>
  <section>
    <table border="1" cellpadding="5">
      <tr>
        <th>Static Web Page</th>
        <th>Dynamic Web Page</th>
      </tr>
      <tr>
        <td>Displays fixed content</td>
        <td>Content is generated dynamically</td>
      </tr>
      <tr>
        <td>Same for all users</td>
        <td>Can vary for different users</td>
      </tr>
      <tr>
        <td>Little or no server processing</td>
        <td>Requires server-side processing</td>
      </tr>
      <tr>
        <td>Simple and faster to load</td>
        <td>Interactive and personalised</td>
      </tr>
      <tr>
        <td>Does not use databases</td>
        <td>Often uses databases and scripting</td>
      </tr>
    </table>
  </section>
</section>

<section>
  <section><h4><b>25) </b>Give two advantages of static web pages. [2]</h4></section>
  <section>
    <p>• They are faster to load because little processing is required</p>
    <p>• They are cheaper and easier to create and maintain</p>
  </section>
</section>

<section>
  <section><h4><b>26) </b>Give two advantages of dynamic web pages. [2]</h4></section>
  <section>
    <p>• They can be personalised for individual users</p>
    <p>• They are easier to update across the entire website (site-wide changes)</p>
  </section>
</section>

<section>
  <section><h4><b>27) </b>Explain why dynamic web pages are more scalable. [2]</h4></section>
  <section>
    <p>• Dynamic web pages are more scalable because content is stored in databases and generated when needed</p>
    <p>• This allows the system to handle large amounts of data and users without creating separate pages for each one</p>
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

