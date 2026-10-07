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
<!-- 1(a)(i) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(a)(i)</b> Name two other tools that are used in project management. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>Gantt chart</p>

        <p>Critical Path Analysis (CPA)</p>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            A Gantt chart is a visual project management tool
            that displays project tasks against time.
        </p>

        <p>
            It helps project managers organise activities,
            monitor deadlines,
            allocate resources,
            and identify overlapping tasks.
        </p>
    </section>

    <section>
        <p>
            A Critical Path Analysis (CPA)
            identifies the sequence of tasks
            that must be completed on time
            for the project to finish on schedule.
        </p>

        <p>
            It helps managers identify essential tasks,
            avoid delays,
            and calculate project completion time.
        </p>

        <p>
            Both tools improve project planning and monitoring.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(a)(ii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(a)(ii)</b> State the meaning of the A in SMART targets. (1 mark)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            The “A” stands for Achievable.
        </p>
    </section>
	
    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            Achievable means the project goals
            must be realistic and possible to complete
            using the available time,
            money,
            staff,
            technology,
            and resources.
        </p>

        <p>
            If a target is not achievable,
            the project may fail
            due to unrealistic expectations.
        </p>
    </section>
	<section>
	<p>S – Specific<br>
		The target should clearly state what needs to be achieved.</p>
<p>M – Measurable
The progress or success can be measured.</p>
<p>A – Achievable
The target should be realistic and possible to complete.</p>
<p>R – Relevant
The target should relate to the task, project, or business objective.</p>
<p>T – Time-bound
The target should have a deadline or time limit.</p>
	</section>
</section>

<!-- ====================================================== -->
<!-- 1(a)(iii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(a)(iii)</b> Give one reason for using SMART targets to define project outcomes. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            SMART targets clearly define
            the criteria for project success.
        </p>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            SMART targets help project teams understand:
        </p>

        <ul>
            <li>What needs to be completed</li>
            <li>When it must be completed</li>
            <li>How success will be measured</li>
        </ul>

        <p>
            This improves communication
            and allows project progress
            to be monitored effectively
            throughout development.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(b) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(b)</b> Complete the diagram to show the waterfall method of systems development. (6 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p><b>Correct order:</b></p>

        <ol>
            <li>Requirements and Analysis</li>
            <li>Design</li>
            <li>Implementation</li>
            <li>Testing/Debugging</li>
            <li>Installation</li>
            <li>Maintenance</li>
        </ol>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            The Waterfall Model is a sequential
            software development method
            where each stage must be completed
            before the next begins.
        </p>
    </section>

    <section>
        <p><b>Stages Explained:</b></p>

        <p><b>1. Requirements and Analysis</b></p>

        <p>
            Developers gather information about:
        </p>

        <ul>
            <li>User needs</li>
            <li>Business requirements</li>
            <li>System objectives</li>
        </ul>
    </section>

    <section>
        <p><b>2. Design</b></p>

        <p>
            The system structure is planned,
            including:
        </p>

        <ul>
            <li>Database design</li>
            <li>Interfaces</li>
            <li>Algorithms</li>
            <li>Hardware requirements</li>
        </ul>
    </section>

    <section>
        <p><b>3. Implementation</b></p>

        <p>
            Programmers write the actual code
            for the software system.
        </p>
    </section>

    <section>
        <p><b>4. Testing/Debugging</b></p>

        <p>
            The software is tested
            to identify and fix:
        </p>

        <ul>
            <li>Bugs</li>
            <li>Errors</li>
            <li>Security issues</li>
            <li>Performance problems</li>
        </ul>
    </section>

    <section>
        <p><b>5. Installation</b></p>

        <p>
            The finished software
            is installed for users.
        </p>
    </section>

    <section>
        <p><b>6. Maintenance</b></p>

        <p>
            The system is updated
            and repaired after release.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(c) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(c)</b> Explain one advantage of using containerisation to deploy the finished product to the cloud. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Containerisation ensures the software
            runs correctly in the cloud
            because the application includes
            all required dependencies
            and runtime files.
        </p>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            A container packages:
        </p>

        <ul>
            <li>Application code</li>
            <li>Libraries</li>
            <li>Configuration files</li>
            <li>Runtime environment</li>
        </ul>
    </section>

    <section>
        <p>
            This means the software behaves consistently
            regardless of the operating system
            or hardware used in the cloud environment.
        </p>
    </section>

    <section>
        <p>
            Without containerisation:
        </p>

        <ul>
            <li>Missing libraries</li>
            <li>Different operating systems</li>
            <li>Incompatible configurations</li>
        </ul>

        <p>
            could cause software failures.
        </p>
    </section>

    <section>
        <p>
            Containerisation improves:
        </p>

        <ul>
            <li>Portability</li>
            <li>Reliability</li>
            <li>Deployment speed</li>
            <li>Scalability</li>
        </ul>
    </section>
</section>
<section>
	<section>
		<h4><b>1(d)</b> An aeroplane manufacturer is considering developing a virtual reality flight
trainer for its aeroplanes.
Discuss the benefits and drawbacks of using virtual reality in the flight trainer(6 marks)</h4>
	</section>
	<section>
    <p>
        Virtual reality (VR) can provide many advantages when used in a flight trainer for aeroplanes, but there are also some disadvantages that the manufacturer must consider.
    </p>
</section>

<section>
    <p>
        One major benefit is that VR improves safety during training. Pilots can practise difficult manoeuvres and emergency situations, such as engine failure or bad weather conditions, without risking lives or damaging a real aeroplane. This allows trainees to gain experience in dangerous situations safely.
    </p>
</section>

<section>
    <p>
        Another advantage is that VR can reduce training costs in the long term. Using a real aeroplane for training requires fuel, maintenance, airport charges, and repair costs if accidents occur. A VR simulator can be reused many times to train different pilots and engineers, making it more cost-effective over time.
    </p>
</section>

<section>
    <p>
        VR training can also save time and increase convenience. Pilots can practise take-offs and landings at airports around the world without travelling there physically. In addition, training can begin even before the actual aeroplane has been fully built, allowing staff to prepare earlier.
    </p>
</section>

<section>
    <p>
        Furthermore, VR systems can simulate many different aircraft models, cockpit layouts, and emergency scenarios. Haptic devices can also provide realistic feedback, helping users feel as if they are operating a real aircraft.
    </p>
</section>

<section>
    <p>
        However, there are drawbacks to using VR in flight training. One disadvantage is the high development cost. Designing and building a realistic VR simulator requires expensive hardware such as headsets, sensors, displays, and haptic equipment, as well as specialist software developers and engineers.
    </p>
</section>

<section>
    <p>
        Another drawback is that VR may not perfectly replicate real-life flying conditions. The controls and physical sensations inside a simulator may feel different from an actual aeroplane, so pilots may not gain the full real-world experience.
    </p>
</section>

<section>
    <p>
        In addition, some users may experience side effects such as dizziness, nausea, headaches, or eye strain when using VR equipment for long periods of time. This could affect the effectiveness of the training.
    </p>
</section>

<section>
    <p>
        Overall, VR flight trainers provide safer, flexible, and cost-effective training opportunities, but they are expensive to develop and may not fully replace real flying experience.
    </p>
</section>
		</section>
 
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2023_m_u3_q2.php">next</a></p>
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

