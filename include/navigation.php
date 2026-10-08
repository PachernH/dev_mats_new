<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
ul, #myUL {
  list-style-type: none;
}

#myUL {
  margin: 0;
  padding: 0;
}

.box {
  cursor: pointer;
  -webkit-user-select: none; /* Safari 3.1+ */
  -moz-user-select: none; /* Firefox 2+ */
  -ms-user-select: none; /* IE 10+ */
  user-select: none;
}

.box::before {
  content: "\2610";
  color: black;
  display: inline-block;
  margin-right: 6px;
}

.check-box::before {
  content: "\2611"; 
  color: dodgerblue;
}

.nested {
  display: none;
}

.active {
  display: block;
}
</style>
</head>
<body>
<div class="sidebar" data-color="azure" data-image="assets/img/full-screen-mats.png" id="side_nav">
<div class="sidebar-wrapper">
<div class="logo"><img src="assets/img/logo-mat.png"></div>

<?php echo $_SESSION['id'] ?>
</div>
</div>

<script>
var toggler = document.getElementsByClassName("box");
var i;

for (i = 0; i < toggler.length; i++) {
  toggler[i].addEventListener("click", function() {
    this.parentElement.querySelector(".nested").classList.toggle("active");
    this.classList.toggle("check-box");
  });
}
</script>
</body>
</html>