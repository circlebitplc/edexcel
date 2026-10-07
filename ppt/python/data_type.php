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

	<meta name='description' content='python data types'>
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
					<h4>Python</h4>
					<h2>data types</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>  
 <section>
    <h1>Python Data Types</h1>
    <p>Understanding How Python Stores and Processes Data</p>
</section>

<section>
    <h2>What is a Data Type?</h2>
    <p>
        A data type defines the kind of value stored in a variable.
        It tells Python how the data should be stored, processed,
        and manipulated.
    </p>
</section>

<section>
    <h2>Why Are Data Types Important?</h2>
    <ul>
        <li>Ensure data is stored correctly</li>
        <li>Allow appropriate operations</li>
        <li>Improve program efficiency</li>
        <li>Reduce programming errors</li>
        <li>Help Python manage memory effectively</li>
    </ul>
</section>

<section>
    <h2>Examples of Different Data Types</h2>

<pre><code>
name = "John"
age = 18
height = 1.75
is_student = True
</code></pre>

    <ul>
        <li>String → "John"</li>
        <li>Integer → 18</li>
        <li>Float → 1.75</li>
        <li>Boolean → True</li>
    </ul>
</section>

<section>
    <h2>Dynamic Typing in Python</h2>

    <p>
        Python automatically determines the data type when a value
        is assigned to a variable.
    </p>

<pre><code>
x = 100
print(type(x))

x = "Python"
print(type(x))
</code></pre>
</section>

<section>
    <h2>Main Categories of Data Types</h2>

    <table>
        <tr>
            <th>Category</th>
            <th>Types</th>
        </tr>
        <tr>
            <td>Numeric</td>
            <td>int, float, complex</td>
        </tr>
        <tr>
            <td>Text</td>
            <td>str</td>
        </tr>
        <tr>
            <td>Boolean</td>
            <td>bool</td>
        </tr>
        <tr>
            <td>Sequence</td>
            <td>list, tuple, range</td>
        </tr>
        <tr>
            <td>Mapping</td>
            <td>dict</td>
        </tr>
        <tr>
            <td>Set</td>
            <td>set</td>
        </tr>
    </table>
</section>

<section>
    <section><h2>Integer (int)</h2>

		<p>
			Stores whole numbers without decimal points.
		</p>

	<pre><code>
	age = 18
	temperature = -5
	population = 22000000
	</code></pre>

    <p>Examples: 10, -50, 1000, 0</p></section>
	<section>
	<img src="<?= $dirBase ?>/str.jpeg">
	</section>
	<section>
		<h2>Uses of Integers</h2>

		<ul>
			<li>Counting students</li>
			<li>Age values</li>
			<li>Inventory quantities</li>
			<li>Loop counters</li>
			<li>Exam marks</li>
		</ul>
	</section>
</section>



<section>
	<section>
		<h2>Float (float)</h2>

		<p>
			Stores numbers containing decimal places.
		</p>

	<pre><code>
	height = 1.75
	price = 1500.99
	pi = 3.14159
	</code></pre>
	</section> 
	<section>
	<img src="<?= $dirBase ?>/float.jpeg">
	</section>
	<section>
    <h2>Uses of Floats</h2>

    <ul>
        <li>Measurements</li>
        <li>Financial calculations</li>
        <li>Scientific calculations</li>
        <li>Percentages</li>
        <li>Temperature values</li>
    </ul>
	</section>
</section>

<section>
	<section>
    <h2>Complex Numbers</h2>

	<pre><code>
	z = 3 + 4j
	</code></pre>

		<p>
			Used mainly in engineering, physics and scientific computing.
		</p>

		<ul>
			<li>3 → Real Part</li>
			<li>4j → Imaginary Part</li>
		</ul>
	</section>
	<section>
	<img src="<?= $dirBase ?>/complex.jpeg">
	</section>
</section>

<section>
	<section>
    <h2>String (str)</h2>

    <p>
        A sequence of characters enclosed in quotation marks.
    </p>

	<pre><code>
	name = "Alice"
	city = "London"
	</code></pre>

		<p>
			Strings store textual information.
		</p>
	</section>
	<section> 
		<img src="<?= $dirBase ?>/str.jpeg">
	</section>

<section>
    <h2>String Operations</h2>

<pre><code>
first = "Hello"
second = "World"

print(first + " " + second)
</code></pre>

    <p>Output: Hello World</p>
</section>

<section>
    <h2>Accessing Characters</h2>

<pre><code>
word = "Python"

print(word[0])
print(word[1])
</code></pre>

    <p>Output:</p>

<pre><code>
P
y
</code></pre>
</section>
</section>
<section>
	<section>
		<h2>Boolean (bool)</h2>

		<p>
			Stores only two possible values.
		</p>

		<ul>
			<li>True</li>
			<li>False</li>
		</ul>

		<pre><code>
		is_admin = True
		logged_in = False
		</code></pre>
	</section>
	<section>
	<img src="<?= $dirBase ?>/bool.jpeg">
	</section>
	<section>
		<h2>Boolean Example</h2>

	<pre><code>
	age = 20

	print(age >= 18)
	</code></pre>

		<p>Output: True</p>
	</section>
</section>
<section>
	<section>
		<h2>List</h2>

		<p>
			An ordered collection that can be modified.
		</p>

	<pre><code>
	fruits = ["Apple", "Orange", "Banana"]
	</code></pre>

		<h4>Characteristics</h4>

		<ul>
			<li>Ordered</li>
			<li>Mutable</li>
			<li>Allows duplicates</li>
		</ul>
	</section>
	<section>
		<img src="<?= $dirBase ?>/list.jpeg">
	</section>
	<section>
		<h2>List Methods</h2>

	<pre><code>
	fruits.append("Mango")
	fruits.remove("Orange")
	</code></pre>

		<p>
			Lists are commonly used to store collections of related items.
		</p>
	</section>
</section>
<section>
	<section>
		<h2>Tuple</h2>

		<p>
			Similar to a list but cannot be changed after creation.
		</p>

	<pre><code>
	days = ("Mon", "Tue", "Wed")
	</code></pre>

		<ul>
			<li>Ordered</li>
			<li>Immutable</li>
			<li>Allows duplicates</li>
		</ul>
	</section>
	<section>
	<img src="<?= $dirBase ?>/tuple.jpeg">
	</section>
</section>
<section>
    <h2>Dictionary (dict)</h2>

    <p>
        Stores data as key-value pairs.
    </p>

<pre><code>
student = {
    "name": "John",
    "age": 18,
    "grade": "A"
}
</code></pre>
</section>

<section>
    <h2>Accessing Dictionary Values</h2>

<pre><code>
print(student["name"])
</code></pre>

    <p>Output: John</p>
</section>

<section>
    <h2>Set</h2>

    <p>
        Stores unique values only.
    </p>

<pre><code>
numbers = {1,2,3,4}
</code></pre>

    <ul>
        <li>No duplicates allowed</li>
        <li>Unordered collection</li>
    </ul>
</section>

<section>
    <h2>Set Example</h2>

<pre><code>
numbers = {1,1,2,2,3,3}
print(numbers)
</code></pre>

    <p>Output:</p>

<pre><code>
{1,2,3}
</code></pre>
</section>

<section>
    <h2>Range</h2>

<pre><code>
for i in range(5):
    print(i)
</code></pre>

    <p>
        Generates a sequence of numbers.
    </p>
</section>

<section>
    <h2>Checking Data Types</h2>

<pre><code>
name = "Python"
print(type(name))
</code></pre>

    <p>Output:</p>

<pre><code>
<class 'str'>
</code></pre>
</section>

<section>
    <h2>Type Conversion</h2>

<pre><code>
age = "18"
age = int(age)

price = "15.50"
price = float(price)
</code></pre>

    <p>
        Converts data from one type to another.
    </p>
</section>

<section>
    <h2>Common Conversion Functions</h2>

    <table>
        <tr>
            <th>Function</th>
            <th>Purpose</th>
        </tr>
        <tr>
            <td>int()</td>
            <td>Convert to Integer</td>
        </tr>
        <tr>
            <td>float()</td>
            <td>Convert to Float</td>
        </tr>
        <tr>
            <td>str()</td>
            <td>Convert to String</td>
        </tr>
        <tr>
            <td>bool()</td>
            <td>Convert to Boolean</td>
        </tr>
    </table>
</section>

<section>
    <h2>Real-World Example</h2>

<pre><code>
name = "Sarah"
age = 17
average = 82.5
passed = True

subjects = ["ICT", "Maths", "English"]
</code></pre>

    <p>
        Most Python programs use multiple data types together.
    </p>
</section>

<section>
    <h2>Summary</h2>

    <ul>
        <li>int → Whole Numbers</li>
        <li>float → Decimal Numbers</li>
        <li>complex → Complex Numbers</li>
        <li>str → Text</li>
        <li>bool → True or False</li>
        <li>list → Mutable Collection</li>
        <li>tuple → Immutable Collection</li>
        <li>dict → Key-Value Pairs</li>
        <li>set → Unique Values</li>
    </ul>
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

