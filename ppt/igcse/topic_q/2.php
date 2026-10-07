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

	<meta name='description' content='Chapter 2 – Software'>
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
					<h4>Chapter 2</h4>
					<h2>Software</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

<section>
  <h3>Section A — Multiple-Choice Questions</h3>
</section>

<!-- Question 1 -->
<section>
  <section>
    <h4><b>1)</b> Utility software is used to:</h4>
    <ol type="A">
      <li>Create documents, presentations and spreadsheets for users</li>
      <li>Maintain, manage and optimise the computer system</li>
      <li>Translate high-level programming languages into machine code</li>
      <li>Provide online communication between users over the internet</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: B.</strong> Maintain, manage and optimise the computer system</p>
  </section>
</section>


<!-- Question 2 -->
<section>
  <section>
    <h4><b>2)</b> Defragmentation works by:</h4>
    <ol type="A">
      <li>Deleting unused files to increase the total storage capacity</li>
      <li>Compressing files so that they occupy fewer disk sectors</li>
      <li>Reorganising fragmented parts of files so they are stored closer together</li>
      <li>Encrypting files so that unauthorised users cannot access them</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: C.</strong> Reorganising fragmented parts of files so they are stored closer together</p>
  </section>
</section>


<!-- Question 3 -->
<section>
  <section>
    <h4><b>3)</b> Which utility software makes the storage device unreadable?</h4>
    <ol type="A">
      <li>Defragmentation</li>
      <li>Formatting</li>
      <li>File compression</li>
      <li>Backup software</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: B.</strong> Formatting</p>
  </section>
</section>


<!-- Question 4 -->
<section>
  <section>
    <h4><b>4)</b> Which of the following is an application software?</h4>
    <ol type="A">
      <li>Device driver</li>
      <li>Operating system</li>
      <li>Word processor</li>
      <li>Disk formatting utility</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: C.</strong> Word processor</p>
  </section>
</section>


<!-- Question 5 -->
<section>
  <section>
    <h4><b>5)</b> Which of the following does NOT require an internet connection?</h4>
    <ol type="A">
      <li>Video conferencing</li>
      <li>Cloud-based file sharing</li>
      <li>MMS</li>
      <li>Offline word processing</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: C.</strong> MMS</p>
  </section>
</section>


<!-- Question 6 -->
<section>
  <section>
    <h4><b>6)</b> Project management software can:</h4>
    <ol type="A">
      <li>Replace the operating system and manage CPU scheduling</li>
      <li>Track resources, tasks, milestones and project deadlines</li>
      <li>Convert all application software into open-source software</li>
      <li>Physically increase the amount of RAM installed in a computer</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: B.</strong> Track resources, tasks, milestones and project deadlines</p>
  </section>
</section>


<!-- Question 7 -->
<section>
  <section>
    <h4><b>7)</b> Open-source software allows users to:</h4>
    <ol type="A">
      <li>Modify and redistribute the source code according to its licence</li>
      <li>Use the software only when connected to the developer's server</li>
      <li>Access the source code but never make any changes to it</li>
      <li>Redistribute the software without following any licence conditions</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: A.</strong> Modify and redistribute the source code according to its licence</p>
  </section>
</section>


<!-- Question 8 -->
<section>
  <section>
    <h4><b>8)</b> Which communication method has a 160-character limit?</h4>
    <ol type="A">
      <li>Multimedia Messaging Service (MMS)</li>
      <li>Instant messaging</li>
      <li>Short Message Service (SMS)</li>
      <li>Video conferencing</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: C.</strong> Short Message Service (SMS)</p>
  </section>
</section>


<!-- Question 9 -->
<section>
  <section>
    <h4><b>9)</b> A network operating system must:</h4>
    <ol type="A">
      <li>Allow only one user to access the network at a time</li>
      <li>Allow multiple users to have separate accounts and controlled access</li>
      <li>Prevent computers from sharing files and network resources</li>
      <li>Remove the need for authentication and user permissions</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: B.</strong> Allow multiple users to have separate accounts and controlled access</p>
  </section>
</section>


<!-- Question 10 -->
<section>
  <section>
    <h4><b>10)</b> Which software licence category includes "free but proprietary" software?</h4>
    <ol type="A">
      <li>Open-source software</li>
      <li>Freeware</li>
      <li>Public-domain software</li>
      <li>Shareware</li>
    </ol>
  </section>

  <section>
    <p><strong>Answer: B.</strong> Freeware</p>
  </section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define system software.</h4></section>
  <section><p>System software is software that manages hardware resources and provides a platform for application software to run, such as an operating system.</p></section>
</section>

<section>
  <section><h4><b>12) </b>What does compression do to a file?</h4></section>
  <section><p>Compression reduces the file size by removing redundant data so that it uses less storage space.</p></section>
</section>

<section>
  <section><h4><b>13) </b>Why is backup software important?</h4></section>
  <section><p>Backup software protects data by creating copies that can be restored if data is lost, corrupted, or deleted.</p></section>
</section>

<section>
  <section><h4><b>14) </b>Explain what print spooling is.</h4></section>
  <section><p>Print spooling stores print jobs temporarily in a queue so users can continue working while documents are printed one at a time.</p></section>
</section>

<section>
  <section><h4><b>15) </b>State one benefit of having separate user accounts in a network OS.</h4></section>
  <section><p>It improves security by preventing unauthorised access to other users’ files.</p></section>
</section>

<section>
  <section><h4><b>16) </b>Give two examples of office productivity software.</h4></section>
  <section><p>Word processing software</p></section>
  <section><p>Spreadsheet software</p></section>
</section>

<section>
  <section><h4><b>17) </b>What is the purpose of speaker notes in presentation software?</h4></section>
  <section><p>Speaker notes provide guidance for the presenter without being visible to the audience.</p></section>
</section>

<section>
  <section><h4><b>18) </b>Describe the difference between web authoring and desktop publishing.</h4></section>
  <section><p>Web authoring is used to create websites for online viewing, while desktop publishing is used to design printed documents such as brochures and magazines.</p></section>
</section>

<section>
  <section><h4><b>19) </b>Why should users back up data before software updates?</h4></section>
  <section><p>In case the update fails or causes data loss, the backed-up data can be restored.</p></section>
</section>

<section>
  <section><h4><b>20) </b>What is source code?</h4></section>
  <section><p>Source code is the original human-readable programming code written by developers.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Compare Utility Software and Application Software.</h4></section>
  <section><p>Utility software maintains and manages the system, while application software performs user tasks.</p></section>
  <section><p>Utility software works in the background, whereas application software is user-focused.</p></section>
  <section><p>Examples of utility software include backup and antivirus, while application software includes word processors and spreadsheets.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Describe the four types of utility software.</h4></section>
  <section><p>Backup creates copies of data for recovery.</p></section>
  <section><p>Defragmentation reorganises scattered files on a disk.</p></section>
  <section><p>Compression reduces file size.</p></section>
  <section><p>Formatting prepares a storage device for use by erasing existing data.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Explain four tasks performed by a Network Operating System.</h4></section>
  <section><p>Resource management controls access to files and printers.</p></section>
  <section><p>Memory management allocates memory to users and processes.</p></section>
  <section><p>Print spooling manages multiple print jobs.</p></section>
  <section><p>Security manages user authentication and permissions.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Explain the difference between SMS, MMS, and Instant Messaging.</h4></section>
  <section><p>SMS sends short text messages with a character limit.</p></section>
  <section><p>MMS sends multimedia content such as images and videos.</p></section>
  <section><p>Instant messaging allows real-time communication over the internet.</p></section>
</section>

<section>
  <section><h4><b>25) </b>Explain the purpose of each type of software license.</h4></section>
  <section><p>Open-source allows users to modify and redistribute software.</p></section>
  <section><p>Free software allows free use and modification.</p></section>
  <section><p>Proprietary software restricts copying and modification.</p></section>
  <section><p>Freeware is free to use but cannot be modified.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Identify the utility software needed.</h4></section>
  <section><p>Defragmentation software</p></section>
  <section><p>It improves performance by reorganising fragmented files so the disk accesses data faster.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Posters and brochures software choice.</h4></section>
  <section><p>Desktop publishing software should be used.</p></section>
  <section><p>It provides advanced layout tools and professional print features.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Remote communication and file sharing.</h4></section>
  <section><p>Instant messaging and video conferencing software are suitable.</p></section>
  <section><p>They allow real-time communication and fast file sharing.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Software modification and resale.</h4></section>
  <section><p>An open-source license allows legal modification and redistribution.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Apps stop working after OS update.</h4></section>
  <section><p>The applications may be incompatible with the new OS.</p></section>
  <section><p>Required drivers or system libraries may no longer be supported.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Role of the operating system.</h4></section>
  <section><p>The operating system manages hardware resources such as CPU, memory, and storage.</p></section>
  <section><p>It provides a user interface for interaction.</p></section>
  <section><p>It controls security, file management, and application execution.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Importance of project management software.</h4></section>
  <section><p>It helps plan tasks, manage resources, and track deadlines.</p></section>
  <section><p>It keeps projects on schedule and within budget.</p></section>
  <section><p>It improves coordination between teams.</p></section>
</section>

<section>
  <section><h4><b>33) </b>OS, utilities, and applications in smartphones.</h4></section>
  <section><p>The operating system controls hardware and user interaction.</p></section>
  <section><p>Utility tools maintain performance and security.</p></section>
  <section><p>Applications allow users to perform tasks such as communication and productivity.</p></section>
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

