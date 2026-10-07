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
					<h4>2022 May</h4>
					<h2> Unit 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>   
 <!-- ====================================================== -->
<!-- 4(a)(i) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>4(a)(i)</b> Explain one way customer services could use two CRM fields. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Fields 7 and 8 could be used
            to identify poor customer service performance.
        </p>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            Field 7 stores waiting times
            in the customer service queue.
        </p>

        <p>
            Field 8 stores the time taken
            to solve customer problems.
        </p>
    </section>

    <section>
        <p>
            Managers can analyse these fields to:
        </p>

        <ul>
            <li>Identify delays</li>
            <li>Improve staffing levels</li>
            <li>Reduce waiting times</li>
            <li>Improve customer satisfaction</li>
        </ul>
    </section>
</section>

<!-- ====================================================== -->
<!-- 4(a)(ii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>4(a)(ii)</b> Explain one way marketing could use three CRM fields. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Fields 1, 2, and 3
            could be used to identify customers
            who have not visited recently
            and send targeted promotions.
        </p>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            Field 1 identifies the customer.
        </p>

        <p>
            Field 2 shows the last visit date.
        </p>

        <p>
            Field 3 records products purchased.
        </p>
    </section>

    <section>
        <p>
            Marketing staff can send personalised advertisements
            based on previous buying behaviour
            to encourage repeat purchases.
        </p>
    </section>

    <section>
        <p>
            This improves:
        </p>

        <ul>
            <li>Customer retention</li>
            <li>Targeted advertising</li>
            <li>Sales revenue</li>
        </ul>
    </section>
</section>

<!-- ====================================================== -->
<!-- 4(b) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>4(b)</b> Identify the changeover methods. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Scenario</th>
                <th>Changeover Method</th>
            </tr>

            <tr>
                <td>Old and new systems run together</td>
                <td>Parallel</td>
            </tr>

            <tr>
                <td>One location tests new system first</td>
                <td>Pilot</td>
            </tr>

            <tr>
                <td>Different systems changed gradually</td>
                <td>Phased</td>
            </tr>

            <tr>
                <td>Old system removed immediately</td>
                <td>Direct</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p><b>Parallel Changeover</b></p>

        <p>
            Both systems run simultaneously,
            reducing risk but increasing costs.
        </p>
    </section>

    <section>
        <p><b>Pilot Changeover</b></p>

        <p>
            One department or location
            tests the system
            before full rollout.
        </p>
    </section>

    <section>
        <p><b>Phased Changeover</b></p>

        <p>
            Systems are introduced gradually
            in stages.
        </p>
    </section>

    <section>
        <p><b>Direct Changeover</b></p>

        <p>
            The old system is replaced immediately,
            which is faster but riskier.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 4(c) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>4(c)</b> Discuss the need for and features of risk management and disaster recovery policies. (12 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Businesses depend heavily on IT systems,
            so risk management and disaster recovery policies
            are essential.
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- Risk Management -->
    <!-- ====================================================== -->

    <section>
        <p><b>Risk Management</b></p>

        <p><b>Purpose</b></p>

        <p>
            Risk management identifies and controls threats to:
        </p>

        <ul>
            <li>Data</li>
            <li>Systems</li>
            <li>Networks</li>
            <li>Operations</li>
        </ul>
    </section>

    <section>
        <p><b>Features of Risk Management Policies</b></p>

        <ul>
            <li>Identification of risks</li>
            <li>Risk classification</li>
            <li>Monitoring procedures</li>
            <li>Response plans</li>
            <li>Staff responsibilities</li>
            <li>Regular reviews</li>
        </ul>
    </section>

    <section>
        <p><b>Importance</b></p>

        <p>
            Risk management helps organisations:
        </p>

        <ul>
            <li>Reduce cyber attacks</li>
            <li>Avoid downtime</li>
            <li>Protect sensitive data</li>
            <li>Maintain business continuity</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- Disaster Recovery -->
    <!-- ====================================================== -->

    <section>
        <p><b>Disaster Recovery</b></p>

        <p><b>Purpose</b></p>

        <p>
            Disaster recovery ensures systems
            can recover after:
        </p>

        <ul>
            <li>Cyber attacks</li>
            <li>Fires</li>
            <li>Floods</li>
            <li>Hardware failures</li>
            <li>Accidental damage</li>
        </ul>
    </section>

    <section>
        <p><b>Features of Disaster Recovery Policies</b></p>

        <ul>
            <li>Backup procedures</li>
            <li>Off-site storage</li>
            <li>Restoration processes</li>
            <li>Replacement equipment plans</li>
            <li>Emergency contacts</li>
            <li>Staff responsibilities</li>
        </ul>
    </section>

    <section>
        <p><b>Importance</b></p>

        <p>
            Disaster recovery minimises:
        </p>

        <ul>
            <li>Financial loss</li>
            <li>Downtime</li>
            <li>Operational disruption</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- Conclusion -->
    <!-- ====================================================== -->

    <section>
        <p><b>Conclusion</b></p>

        <p>
            Both policies are essential
            because risk management helps prevent problems,
            while disaster recovery ensures the business
            can recover quickly if a disaster occurs.
        </p>
    </section>
</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2023_m_u3_q5.php">next</a></p>
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

