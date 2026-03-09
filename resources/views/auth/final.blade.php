<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Commission - Calculator</title>
        <!-- Favicon-->



        <style>
            .btn-default {
    color: #333;
    background-color: #fff;
    border-color: #ccc;
    padding: 6px 12px;
    border-style: solid;
    border-width: 1px;
}

input {
 display: none;
}

input:checked + label{
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}
        </style>
        <link rel="icon" type="image/x-icon" href="demo/assets/BP_Gradient_White_1.png" />

        <link href='https://fonts.googleapis.com/css?family=Manrope' rel='stylesheet'>

        <!-- Font Awesome icons (free version)-->
        <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
        <!-- Google fonts-->
        <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css?family=Lato:400,700,400italic,700italic" rel="stylesheet" type="text/css" />
        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="demo/css/styles.css" rel="stylesheet" />
        <link href="demo/css/newstyle.css" rel="stylesheet" />
        <script src = "https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
		<meta name="csrf-token" content="{{ csrf_token() }}" />
        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
        <!-- Latest compiled and minified JavaScript -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
    </head>
    <body id="page-top">
        <header class="masthead header-bg text-white text-center">
            <div class="container d-flex align-items-center flex-column">
                <!-- Masthead Avatar Image-->
                <img class="masthead-avatar mb-5" src="demo/assets/BP_Gradient_White_1.png" alt="" />
                <!-- Masthead Heading-->
                <h3 class="masthead-heading mb-0">Commission Calculator</h3>              
            </div>
        </header>
        <!-- Portfolio Section-->
        <section class="page-section portfolio" id="portfolio">
            <div class="container">
                <div class="col-md-6" style="margin-top: 10px;">
                    </ul>
                    <div class="tab-content">
                      <div class="tab-pane container active" id="msg">
                        <div class="row" style="margin-top: 0px;">
                            <div class="col-md-6" style=" font-family: 'Manrope';">
								<form id="form">
                                    {{-- <div id="my-input-container"> --}}
                                    <input id="value_type" value="gross" name="gross" hidden>
                                    
                                    <div class="form-group row">
                                        <div class="col-md-5  ">
                                            <label for="name" id="pw">Gross PPW </label> <span class="gray-text">(Price per Watt)</span><br>
                                              <div class="btn-group" data-toggle="buttons">
                                                <label id="btngross" class="btn btn-primary active">
                                                  <input type="radio" checked  onclick="gross()" data-bs-toggle="pill" id="ppw" name="gross" value="gross"> <a class="nav-link active" onclick="gross()" data-bs-toggle="pill" href="#" id="ppw">Gross</a>
                                                </label>
                                                <label id="btnnet" class="btn btn-default" >
                                                  <input type="radio" onclick="net()" data-bs-toggle="pill" id="ppw1" name="gross" value="net"> <a class="nav-link"  onclick="net()" data-bs-toggle="pill" href="#pro" id="ppw1">&nbsp; Net &nbsp;</a>
                                                </label>
                                              </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-row w-250px bor " id="my-input-container">
                                                {{-- <input type="text" id="email" name="PPW" class="form-control" style="height: 44px; border:0;" required oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                             <span class="wa">/Watt</span> --}}
                                             <input type="text" id="email" name="PPW" class="form-control" style="height: 44px; margin: -1px -2px -2px -5px;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');" required>
                                             <span class="wa">/Watt</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="dealer_div" class="form-group row">
                                        <div class="col-md-5">
                                            <label for="lastName"style="margin: 12px;">Dealer Fees</label>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-row w-250px bor ">
											{{-- <input type="text" id="my-input1" name="dealer"  class="form-control" style="height: 44px; border:0;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                            <span class="wa">%</span> --}}
                                            <input type="text" id="my-input1" name="dealer" class="form-control" style="height: 44px; margin: -1px -2px -2px -5px;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                            <span class="wa">%</span>
                                        </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="col-md-5">
                                            <label for="email"style="margin: 12px;">Adders <span class="gray-text">(if any)</span></label>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-row w-250px bor ">
                                                {{-- <input type="text" id="my-input2" name="adders" class="form-control" style="height: 44px;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                                <span class="wa">$</span> --}}
                                                <input type="text" id="my-input2" name="adders" class="form-control" style="height: 44px; margin: -1px -2px -2px -5px;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                                <span class="wa">$</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="col-md-5">
                                            <label for="phone"style="margin: 12px;">Your Redline</label>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-row w-250px bor ">
                                                {{-- <input type="text" id="my-input3" name="redline" class="form-control" style="height: 44px;" oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                                <span class="wa">/Watt</span> --}}
                                                <input type="text" id="my-input3" name="redline" class="form-control" style="height: 44px; margin: -1px -2px -2px -5px;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                                <span class="wa">/Watt</span>
                                              </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="col-md-5">
                                            <label for="phone"style="margin: 12px;">Split Percentage</label>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-row w-250px bor ">
                                            {{-- <input type="text" id="my-input4" name="Percentage" class="form-control" style="height: 44px;" oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                            <span class="wa">%</span> --}}
                                            <input type="text" id="my-input4" name="Percentage" class="form-control" style="height: 44px; margin: -1px -2px -2px -5px;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                            <span class="wa">%</span>
                                        </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="col-md-5">
                                            <label for="phone" style="margin: 12px;">System Size</label>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-row w-250px bor ">
											{{-- <input type="text" id="my-input5" name="system_size" class="form-control" style="height: 44px;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                            <span class="wa">kW</span> --}}
                                            <input type="text" id="my-input5" name="system_size" class="form-control" style="height: 44px; margin: -1px -2px -2px -5px;"  oninput="this.value=this.value.replace(/[^.0-9]/g,'');">
                                            <span class="wa">kW</span>
                                        </div>
                                        </div>
                                    </div>
									<div class="col-md-11 d-flex">
										{{-- <div class="col-md-4"></div> --}}
										<div class="col-md-11"><button class="btn-primary form-control" style="margin: 15px 25px 0px 2px">Calculate</button></div>
									</div>
                                    {{-- </div> --}}
                                </form>

                            </div>                          
                        </div>
                      </div>
                    </div>                   
                </div>
                <br>
                <div class="col-md-4"style=" font-family: 'Poppins'; margin:-16px 0px">
                    <div class="right-tab">
                        <p class="heading">Solar Commission Calculator</p>
                        <p class="discription">This calculator allows the user to see the estimated total commission they will earn on a sale. Simply fill in each field and see your potential earnings.</p>


                        <p class="heading">Gross Vs Net</p>
                        <p class="discription">This tool can calculate commission using Gross or Net EPC. Selecting Gross will also ask you to input the estimated dealer fee. However, both options allow for Adders to be included in the equation. </p>

                        <p class="heading">Split Percentage</p>
                        <p class="discription">The split percentage is the portion above your redline you receive. A closer with a 50/50 split with the setter would enter 50%.</p>
                    </div>
                    &nbsp;
                    &nbsp;
                    &nbsp;
                    <div class="left-tab">
                    <img class="masthead-avatar mb-5" style="margin: 0px 0px 0px 75px;" src="demo/assets/r.svg" alt="" />
                    </div>
                </div>

                <div class="col-md-12 d-sm-flex " style="font-family: 'Manrope';margin: -30px 0px 0px 7px;">
                    <p class="commission-text text-center">Anticipated Commission</p>
                    <p class="dollar-value-bg m-9 text-center">$<span  id="pointsperc">00</span></p>
                     <p class="dollar-small-value text-center">$<span class="dollar-small-value-text text-center" id="pointsperc1">00</span> <span class="dollar-gray-value-text">/ kW </span></p>
                </div>
            </div>
            
        </section>

        <script>
        function gross(){
    // alert();
    $("#btngross").attr('class','btn btn-primary');
    $("#btnnet").attr('class','btn btn-default');
    $("#dealer_div").show();
        $("#pw").text('Gross PPW');
        $("#value_type").val('gross');
        $("#email").val("");  
        $("#my-input1").val("");  
        $("#my-input2").val("");  
        $("#my-input3").val("");  
        $("#my-input4").val("");  
        $("#my-input5").val("");  
        $("#pointsperc").text("00");  
        $("#pointsperc1").text("00");  
}
function net(){
    $("#btnnet").attr('class','btn btn-primary');
    $("#btngross").attr('class','btn btn-default');
    $("#dealer_div").hide();
        $("#pw").text('Net PPW');
        $("#value_type").val('net');  
        $("#email").val("");  
        $("#my-input1").val("");  
        $("#my-input2").val("");  
        $("#my-input3").val("");  
        $("#my-input4").val("");  
        $("#my-input5").val(""); 
        $("#pointsperc").text("00");     
        $("#pointsperc1").text("00");    
}


            </script>
<script type="text/javascript">
	$(document).ready(function (abcda) {

    $("#form").on('submit',(function(abcda){
    abcda.preventDefault();
    // alert("hello");
	$.ajaxSetup({
		headers: {
		'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		}
		});
        $.ajax({
            url:"{{ route('calculate-commission.post') }}",
            type:"POST",
            data:new FormData(this),
            contentType:false,
            cache:false,
            processData:false,
            success:function(data){
            	// alert(data);
                // alert(data);
				console.log(data);
				$('#pointsperc').text(data.data1);
				$('#pointsperc1').text(data.data2);
            },
            error:function(){}
        });
    }));	
});
</script>

<script type="text/javascript">
	$(document).ready(function (abcda) {

    $("#form1").on('submit',(function(abcda){
    abcda.preventDefault();
    // alert("hello");
	$.ajaxSetup({
		headers: {
		'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		}
		});
        $.ajax({
            url:"{{ route('calculate-commission.post') }}",
            type:"POST",
            data:new FormData(this),
            contentType:false,
            cache:false,
            processData:false,
            success:function(data){
            	// alert(data);
                // alert(data);
				console.log(data);
				$('#pointsperc').text(data.data1);
				$('#pointsperc1').text(data.data2);
            },
            error:function(){}
        });
    }));	
});

    const inputElement = document.getElementById('my-input');
    const suffixElement = document.getElementById('my-suffix');


inputElement.addEventListener('input', updateSuffix);

updateSuffix();

function updateSuffix() {
  const width = getTextWidth( inputElement.value, '20px arial');
  suffixElement.style.left = width + '10px';
}



function getTextWidth(text, font) {
    // re-use canvas object for better performance
    var canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    var context = canvas.getContext("2d");
    context.font = font;
    var metrics = context.measureText(text);
    return metrics.width;
}

// function gross(){
//     alert();
//     $("#dealer_div").show();
//         $("#pw").text('Gross PPW');
//         $("#value_type").val('gross');  
// }
// function net(){
//     $("#dealer_div").hide();
//         $("#pw").text('Net PPW');
//         $("#value_type").val('net');  
// }

</script>
<script>
    const inputElement1 = document.getElementById('my-input1');
    const suffixElement1 = document.getElementById('my-suffix1');


inputElement1.addEventListener('input', updateSuffix);

updateSuffix();

function updateSuffix() {
  const width = getTextWidth(inputElement1.value, '19px arial');
  suffixElement1.style.left = width + 'px';
}



function getTextWidth(text, font) {
    // re-use canvas object for better performance
    var canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    var context = canvas.getContext("2d");
    context.font = font;
    var metrics = context.measureText(text);
    return metrics.width;
}
    </script>
<script>
    const inputElement2 = document.getElementById('my-input2');
    const suffixElement2 = document.getElementById('my-suffix2');


inputElement2.addEventListener('input', updateSuffix);

updateSuffix();

function updateSuffix() {
  const width = getTextWidth(inputElement2.value, '19px arial');
  suffixElement2.style.left = width + '7px';
}



function getTextWidth(text, font) {
    // re-use canvas object for better performance
    var canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    var context = canvas.getContext("2d");
    context.font = font;
    var metrics = context.measureText(text);
    return metrics.width;
}
    </script>
<script>
    const inputElement3 = document.getElementById('my-input3');
    const suffixElement3 = document.getElementById('my-suffix3');


inputElement3.addEventListener('input', updateSuffix);

updateSuffix();

function updateSuffix() {
  const width = getTextWidth(inputElement3.value, '19px arial');
  suffixElement3.style.left = width + '8px';
}



function getTextWidth(text, font) {
    // re-use canvas object for better performance
    var canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    var context = canvas.getContext("2d");
    context.font = font;
    var metrics = context.measureText(text);
    return metrics.width;
}
    </script>
<script>
    const inputElement4 = document.getElementById('my-input4');
    const suffixElement4 = document.getElementById('my-suffix4');


inputElement4.addEventListener('input', updateSuffix);

updateSuffix();

function updateSuffix() {
  const width = getTextWidth(inputElement4.value, '18px arial');
  suffixElement4.style.left = width + '8px';
}



function getTextWidth(text, font) {
    // re-use canvas object for better performance
    var canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    var context = canvas.getContext("2d");
    context.font = font;
    var metrics = context.measureText(text);
    return metrics.width;
}
    </script>
<script>
    const inputElement5 = document.getElementById('my-input5');
    const suffixElement5 = document.getElementById('my-suffix5');


inputElement5.addEventListener('input', updateSuffix);

updateSuffix();

function updateSuffix() {
  const width = getTextWidth(inputElement5.value, '17px arial');
  suffixElement5.style.left = width + '8px';
}



function getTextWidth(text, font) {
    // re-use canvas object for better performance
    var canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    var context = canvas.getContext("2d");
    context.font = font;
    var metrics = context.measureText(text);
    return metrics.width;
}
    </script>
</script>
		<script src="demo/vendor/jquery/jquery-3.2.1.min.js"></script>
		 <!--===============================================================================================-->
		<script src="demo/vendor/animsition/js/animsition.min.js"></script>
		 <!--===============================================================================================-->
		<script src="demo/vendor/bootstrap/js/popper.js"></script>
		<script src="demo/vendor/bootstrap/js/bootstrap.min.js"></script>
		 <!--===============================================================================================-->
		<script src="demo/vendor/select2/select2.min.js"></script>
		 <!--===============================================================================================-->
		<script src="demo/vendor/daterangepicker/moment.min.js"></script>
		<script src="demo/vendor/daterangepicker/daterangepicker.js"></script>
		 <!--===============================================================================================-->
		<script src="demo/vendor/countdowntime/countdowntime.js"></script>
		 <!--===============================================================================================-->
		<script src="demo/js/main.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="demo/js/scripts.js"></script>
        <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>
    </body>
</html>
 