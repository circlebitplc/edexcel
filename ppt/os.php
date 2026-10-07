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

	<meta name='description' content='Computer Architecture'>
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
					<h2> Concept of Computing</h2><br><h3>part 1</h3>
					<?= ppt_teacher_credit_markup() ?>
				</section>

 <section>
<h2>Process Management</h2>
<p>
by <a href='http://www.hodamapanthiya.com/enidu/' target="_blank">Enidu Batuwanthudawe</a>
</p>
</section>

<section>
<section><h1>Definition of a Process</h1></section>

<section>
<p>A process is the basic unit of execution in a computer system.</p>
<br><br>
<p>A process is a program that is currently being executed.</p>
<br><br>
<p>A process is an active instance of a program. Its execution progresses sequentially from one instruction to the next.</p>
</section>

<section>
<p><strong>It contains the program code and its current activity.</strong></p>
<br><br>
<p>Depending on the operating system (OS), a process may contain one or more threads that execute instructions.</p>
</section>
</section>

<section>
<section>
<p>After a process is created, the operating system performs the following tasks:</p>
<br>
<p>• Loads the process into the main memory.</p>
</section>

<section>
<p>Process in Main Memory</p>
<img src="<?= $pptBase ?>/img/process_inside_si.png" height="450">
</section>

<section>
<h1>Stack</h1>
<p>Stores temporary data such as function parameters, return addresses and local variables.</p>
</section>

<section>
<h1>Heap</h1>
<p>Memory dynamically allocated to a process during execution.</p>
</section>

<section>
<h1>Data</h1>
<p>Stores global and static variables.</p>
</section>

<section>
<h1>Text</h1>
<p>Contains the executable program instructions and the current execution state represented by the processor registers.</p>
</section>
</section>

<section>
<section>
<h1>Program</h1>
<p>A computer program is a collection of instructions that performs a specific task when executed by a computer. A process is an executing instance of a program.</p>
</section>

<section>
<p>A part of a computer program that performs a specific task is called an <strong>algorithm</strong>. A collection of programs, libraries and related data is known as <strong>software</strong>.</p>
</section>
</section>

<section>
<section><h2>Process Life Cycle</h2></section>

<section>
<img src="<?= $pptBase ?>/img/Process_Life_Cycle.gif" height="650" width="100%">
</section>

<section>
<h1>New</h1>
<p>The initial state of a process immediately after it is created.</p>
</section>

<section>
<h1>Running</h1>
<p>After the operating system assigns the process to the CPU, the processor executes its instructions.</p>
</section>

<section>
<h2>Waiting</h2>
<p>If the process must wait for a resource such as user input, a file, or an I/O operation, it enters the waiting state.</p>
</section>

<section>
<h2>Terminated</h2>
<p>When a process finishes execution or is terminated by the operating system, it enters the terminated state and is removed from memory.</p>
</section>
</section>

<section>

<section>
<h2>Process Control Block (PCB)</h2>
</section>

<section>
<p>The Process Control Block (PCB) is a data structure maintained by the operating system for every process. Each PCB is identified using a unique Process ID (PID) and stores all information required to manage the process.</p>
</section>

<section>
<img src="<?= $pptBase ?>/img/pcb_sin.gif" height="450" width="250">
</section>

<section>
<h2>Process State</h2>
<p>The current state of the process, such as New, Ready, Running, Waiting or Terminated.</p>
</section>

<section>
<h2>Process Privileges</h2>
<p>Defines the permissions required to access system resources.</p>
</section>

<section>
<h2>Process ID</h2>
<p>A unique identifier assigned to each process by the operating system.</p>
</section>

<section>
<h2>Stack Pointer</h2>
<p>A register that points to the top of the process stack.</p>
</section>

<section>
<h2>Program Counter</h2>
<p>The Program Counter (PC) stores the address of the next instruction to be executed.</p>
</section>

<section><h2>CPU Registers</h2><p>Stores the current values of processor registers.</p></section>
<section><h2>Memory Management Information</h2><p>Information used to manage the process memory.</p></section>
<section><h2>Scheduling Information</h2><p>Information used by the CPU scheduler.</p></section>
<section><h2>I/O Status Information</h2><p>Stores information about files and input/output devices used by the process.</p></section>

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

