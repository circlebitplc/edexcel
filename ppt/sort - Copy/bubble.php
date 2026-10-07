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

	<meta name='description' content='bubble'>
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
					<h4>sort</h4>
					<h2>bubble sort</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>  
<section>
    <h1>Bubble Sort in Python</h1>
    <h3>Using FOR Loops and WHILE Loops</h3>
</section>

<!-- ====================================================== -->
<!-- INTRODUCTION -->
<!-- ====================================================== -->

<section>
    <h2>What is Bubble Sort?</h2>

    <p>
        Bubble Sort is a simple sorting algorithm
        that repeatedly compares adjacent values
        and swaps them if they are in the wrong order.
    </p>

    <p>
        Larger values gradually move to the end of the list,
        similar to bubbles rising to the surface.
    </p>
</section>

<!-- ====================================================== -->
<!-- HOW IT WORKS -->
<!-- ====================================================== -->

<section>
    <h2>How Bubble Sort Works</h2>

    <ol>
        <li>Compare two adjacent values</li>
        <li>Swap them if they are in the wrong order</li>
        <li>Move to the next pair</li>
        <li>Repeat until the list is sorted</li>
    </ol>
</section>

<!-- ====================================================== -->
<!-- EXAMPLE -->
<!-- ====================================================== -->

<section>
    <h2>Example</h2>

    <p>Unsorted List:</p>

<pre>
[5, 3, 8, 1, 2]
</pre>

    <p>After Bubble Sort:</p>

<pre>
[1, 2, 3, 5, 8]
</pre>
</section>

<!-- ====================================================== -->
<!-- FOR LOOP VERSION -->
<!-- ====================================================== -->

<section>
    <h2>Bubble Sort Using FOR Loops</h2>

<pre>
def bubble_sort(numbers):

    for i in range(len(numbers)):

        for j in range(0, len(numbers)-1):

            if numbers[j] > numbers[j+1]:

                temp = numbers[j]
                numbers[j] = numbers[j+1]
                numbers[j+1] = temp

    return numbers


values = [5, 3, 8, 1, 2]

print(bubble_sort(values))
</pre>
</section>

<!-- ====================================================== -->
<!-- FOR LOOP PSEUDOCODE -->
<!-- ====================================================== -->

<section>
    <h2>FOR Loop Bubble Sort Pseudocode</h2>

<pre>
FOR i ← 0 TO length-1

    FOR j ← 0 TO length-2

        IF array[j] > array[j+1] THEN

            temp ← array[j]
            array[j] ← array[j+1]
            array[j+1] ← temp

        ENDIF

    NEXT j

NEXT i
</pre>
</section>

<!-- ====================================================== -->
<!-- FOR LOOP FLOWCHART -->
<!-- ====================================================== -->

<section>
    <h2>FOR Loop Bubble Sort Flowchart</h2>

<img src="<?= $dirBase ?>/FOR_Loop_Bubble_Sort.png" height="500px">
</section>

<!-- ====================================================== -->
<!-- WHILE LOOP VERSION -->
<!-- ====================================================== -->

<section>
    <h2>Bubble Sort Using WHILE Loops</h2>

<pre>
def bubble_sort(numbers):

    i = 0

    while i < len(numbers):

        j = 0

        while j < len(numbers)-1:

            if numbers[j] > numbers[j+1]:

                temp = numbers[j]
                numbers[j] = numbers[j+1]
                numbers[j+1] = temp

            j += 1

        i += 1

    return numbers


values = [5, 3, 8, 1, 2]

print(bubble_sort(values))
</pre>
</section>

<!-- ====================================================== -->
<!-- WHILE LOOP PSEUDOCODE -->
<!-- ====================================================== -->

<section>
    <h2>WHILE Loop Bubble Sort Pseudocode</h2>

<pre>
i ← 0

WHILE i < length

    j ← 0

    WHILE j < length-1

        IF array[j] > array[j+1] THEN

            temp ← array[j]
            array[j] ← array[j+1]
            array[j+1] ← temp

        ENDIF

        j ← j + 1

    ENDWHILE

    i ← i + 1

ENDWHILE
</pre>
</section>

<!-- ====================================================== -->
<!-- WHILE LOOP FLOWCHART -->
<!-- ====================================================== -->

<section>
    <h2>WHILE Loop Bubble Sort Flowchart</h2>

<img src="<?= $dirBase ?>/WHILE_Loop Bubble_Sort.png" height="500px">
</section>

<!-- ====================================================== -->
<!-- OPTIMISED VERSION -->
<!-- ====================================================== -->

<section>
    <h2>Optimised Bubble Sort</h2>

    <p>
        Standard Bubble Sort continues checking
        even when the list is already sorted.
    </p>

    <p>
        An optimised version stops early
        if no swaps occur during a pass.
    </p>
</section>

<!-- ====================================================== -->
<!-- OPTIMISED PYTHON CODE -->
<!-- ====================================================== -->

<section>
    <h2>Optimised Bubble Sort Python Code</h2>

<pre>
def bubble_sort(numbers):

    for i in range(len(numbers)):

        swapped = False

        for j in range(0, len(numbers)-1-i):

            if numbers[j] > numbers[j+1]:

                temp = numbers[j]
                numbers[j] = numbers[j+1]
                numbers[j+1] = temp

                swapped = True

        if swapped == False:
            break

    return numbers


values = [5, 3, 8, 1, 2]

print(bubble_sort(values))
</pre>
</section>

<!-- ====================================================== -->
<!-- OPTIMISED EXPLANATION -->
<!-- ====================================================== -->

<section>
    <h2>How Additional Iterations are Reduced</h2>

    <ul>
        <li>The variable <b>swapped</b> checks whether a swap happened</li>
        <li>If no swaps occur, the list is already sorted</li>
        <li>The algorithm stops early using <b>break</b></li>
        <li><b>len(numbers)-1-i</b> reduces unnecessary comparisons</li>
    </ul>

    <p>
        This improves efficiency
        and reduces extra iterations.
    </p>
</section>

<!-- ====================================================== -->
<!-- OPTIMISED PSEUDOCODE -->
<!-- ====================================================== -->

<section>
    <h2>Optimised Bubble Sort Pseudocode</h2>

<pre>
FOR i ← 0 TO length-1

    swapped ← FALSE

    FOR j ← 0 TO length-2-i

        IF array[j] > array[j+1] THEN

            temp ← array[j]
            array[j] ← array[j+1]
            array[j+1] ← temp

            swapped ← TRUE

        ENDIF

    NEXT j

    IF swapped = FALSE THEN
        EXIT LOOP
    ENDIF

NEXT i
</pre>
</section>

<!-- ====================================================== -->
<!-- OPTIMISED FLOWCHART -->
<!-- ====================================================== -->

<section>
    <h2>Optimised Bubble Sort Flowchart</h2>

<img src="<?= $dirBase ?>/Optimised_Bubble_Sort.png" height="500px">
</section>

<!-- ====================================================== -->
<!-- ADVANTAGES -->
<!-- ====================================================== -->

<section>
    <h2>Advantages of Bubble Sort</h2>

    <ul>
        <li>Easy to understand</li>
        <li>Simple to program</li>
        <li>Useful for small datasets</li>
    </ul>
</section>

<!-- ====================================================== -->
<!-- DISADVANTAGES -->
<!-- ====================================================== -->

<section>
    <h2>Disadvantages of Bubble Sort</h2>

    <ul>
        <li>Slow for large datasets</li>
        <li>Many unnecessary comparisons</li>
        <li>Inefficient compared to advanced sorting algorithms</li>
    </ul>
</section>

<!-- ====================================================== -->
<!-- CONCLUSION -->
<!-- ====================================================== -->

<section>
    <h2>Conclusion</h2>

    <p>
        Bubble Sort is a beginner-friendly sorting algorithm
        that repeatedly swaps adjacent values.
    </p>

    <p>
        Using optimisation techniques
        can reduce additional iterations
        and improve performance.
    </p>
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

