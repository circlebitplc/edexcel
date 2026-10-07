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

	<meta name='description' content='papers'>
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
					<h4>2020 Oct</h4>
					<h2> Unit 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>   
    <!-- 4(a) -->
    <section>
        <section>
            <h4><b>4(a)</b> Create an information flow diagram to describe this process. (6 marks)</h4>
        </section>

        <section>
            <p><b>Answer:</b></p>

            <table border="1" cellspacing="0" cellpadding="8">

                <tr>
                    <th>Source / Device</th>
                    <th>Data / Information Flow</th>
                    <th>Destination</th>
                </tr>

                <tr>
                    <td>Number Plate Reader</td>
                    <td>Car registration</td>
                    <td>Control Computer</td>
                </tr>

                <tr>
                    <td>Control Computer</td>
                    <td>Registration + current time</td>
                    <td>Ticket Machine</td>
                </tr>

                <tr>
                    <td>Ticket Machine</td>
                    <td>Ticket issued / taken</td>
                    <td>Control Computer</td>
                </tr>

                <tr>
                    <td>Control Computer</td>
                    <td>Raise barrier instruction</td>
                    <td>Entrance Barrier</td>
                </tr>

                <tr>
                    <td>Radar Beam Controller</td>
                    <td>Car passed / beam state</td>
                    <td>Control Computer</td>
                </tr>

                <tr>
                    <td>Control Computer</td>
                    <td>Lower barrier instruction</td>
                    <td>Entrance Barrier</td>
                </tr>

            </table>
        </section>
    </section>

    <!-- 4(b) -->
    <section>
        <section>
            <h4><b>4(b)</b> Discuss the benefits and drawbacks of using the pilot changeover method in this context. (6 marks)</h4>
        </section>

        <section>
            <p><b>Answer:</b></p>

            <p>One advantage of using the pilot changeover method is that the automated parking system is first introduced in only one car park.</p>
 </section> 
		<section> 
            <p>This reduces risk because if the new system fails, the other car parks can continue operating normally.</p>
 </section> 
		<section> 
            <p>Another benefit is that problems with the system can be identified and corrected before the system is implemented across all car parks.</p>
 </section> 
		<section> 
            <p>For example, issues with the number plate recognition system or ticket machine can be fixed during the pilot stage.</p>
 </section> 
		<section> 
            <p>The pilot system also allows staff to become familiar with the new technology and improve training procedures before the full rollout.</p>
 </section> 
		<section> 
            <p>However, there are also drawbacks.</p>
 </section> 
		<section> 
            <p>Running both the old manual system and the new automated system at different locations can increase costs because both systems need to be maintained at the same time.</p>
 </section> 
		<section> 
            <p>The company will also need to compare the performance of the pilot car park with other locations.</p>
 </section> 
		<section> 
            <p>Collecting and analysing this information requires extra time, money, and staff.</p>
 </section> 
		<section> 
            <p>Another disadvantage is that if the pilot system experiences failures, the selected car park may need to close temporarily.</p>
 </section> 
		<section> 
            <p>This could reduce company profits and damage customer satisfaction.</p>
 </section> 
		<section> 
            <p>Finally, pilot changeover usually takes longer than direct changeover because the system must be carefully tested before being introduced everywhere.</p>
        </section>
    </section> 
		<section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2020_o_u3_q5.php">next</a></p>
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

