{{-- {{dd($data->id)}} --}}
<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8">
  <title>sale data</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/modernizr/2.8.3/modernizr.min.js" type="text/javascript"></script>

<link rel='stylesheet' href='//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css'>
<link rel='stylesheet' href='//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css'>
<link rel='stylesheet' href='//cdnjs.cloudflare.com/ajax/libs/jquery.bootstrapvalidator/0.5.0/css/bootstrapValidator.min.css'><link rel="stylesheet" href="./style.css">

</head>
<body>
<!-- partial:index.partial.html -->
<div class="container">

    <form class="well form-horizontal" action=" " method="post"  id="contact_form">
<fieldset>

<!-- Form Name -->
<legend>sales data</legend>

<!-- Text input-->

<div class="form-group">
  <label class="col-md-2 control-label">id</label>  
  <div class="col-md-4 inputGroupContainer">
  <div class="input-group">
  {{-- <span class="input-group-addon"></span> --}}
  <input  name="first_name" placeholder="First Name" class="form-control"  type="text" value="{{$data->id}}">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->

<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label"style="margin: 3px -2px -7px -76px;">weekly sheet id </label> 
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
  {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span> --}}
  <input name="weekly_sheet_id" placeholder="weekly sheet id" class="form-control"  type="text" value="{{$data->weekly_sheet_id }}">
    </div>
  </div>
</div>

<!-- Text input-->
<div class="form-group">
  <label class="col-md-2 control-label">Closer1</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-envelope"></i></span> --}}
  <input name="closer1_id" placeholder="closer1" class="form-control"  type="text" value="{{isset($data->closer1Detail->first_name , $data->closer1Detail->last_name ) ? $data->closer1Detail->first_name . " " .  $data->closer1Detail->last_name : "" }}">
    </div>
  </div>
<!-- </div> -->


<!-- Text input-->
<!--        
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label"style="margin: 3px -2px -7px -76px;">Closer2</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-earphone"></i></span> --}}
  <input name="closer2_id" placeholder="Closer2" class="form-control" type="text" value="{{isset($data->closer2Detail->first_name, $data->closer2Detail->first_name) ? $data->closer2Detail->last_name . "" . $data->closer2Detail->last_name: '' }}">
    </div>
  </div>
</div>

<!-- Text input-->
      
<div class="form-group">
  <label class="col-md-2 control-label">Setter1</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter1_id" placeholder="Setter1" class="form-control" value="{{isset($data->setter1Detail->first_name , $data->setter1Detail->last_name) ? $data->setter1Detail->first_name . " " . $data->setter1Detail->last_name: ''}}" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Closer1 M1</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer1_m1" placeholder="Closer1 M1" value="{{$data->closer1_m1}}" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Closer2 M1</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer2_m1" value="{{$data->closer2_m1}}" placeholder="Closer2 M1" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Setter1 M1</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter1_m1" placeholder="Setter1 M1" value="{{$data->setter1_m1}}" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Setter2 M1</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter2_m1" placeholder="Setter2 M1"  value = "{{$data->setter2_m1}}" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Closer1 M2</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer1_m2" placeholder="Closer1 M2" value="{{$data->closer1_m2}}" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Closer2 M2</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer2_m2" placeholder="Closer2 M2" value="{{$data->closer2_m2}}" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Setter1 M2</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
      
   <input name="setter1_m2" value="{{$data->setter1_m2}}" placeholder="Setter1 M2" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Setter2 M2</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter2_m2" value="{{$data->setter2_m2}}" placeholder="Setter2 M2" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Closer1 Commission</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer1_commission" value="{{$data->closer1_commission}}" placeholder="Closer1 Commission" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Closer2 Commission</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer2_commission" value="{{$data->closer2_commission}}" placeholder="Closer2 Commission" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Setter1 Commission</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter1_commission" value="{{$data->setter1_commission}}" placeholder="Setter1 Commission" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Setter2 Commission</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter2_commission" value="{{$data->setter2_commission}}" placeholder="Setter2 Commission" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Closer1 M1 Paid Status</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer1_m1_paid_status" value="{{isset($data['data1']->account_status) ? $data['data1']->account_status : ""}}" placeholder="Closer1 M1 Paid Status" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Closer2 M1 Paid Status</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer2_m1_paid_status" value="{{isset($data['data2']->account_status) ? $data['data2']->account_status : ""}}" placeholder="Closer2 M1 Paid Status" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Setter1 M1 Paid Status</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter1_m1_paid_status"  value="{{isset($data['data3']->account_status) ? $data['data3']->account_status : ""}}"  placeholder="Setter1 M1 Paid Status" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Setter2 M1 Paid Status</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter2_m1_paid_status" value="{{isset($data['data4']->account_status) ? $data['data4']->account_status : ""}}" placeholder="Setter2 M1 Paid Status" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Closer1 M2 Paid Status</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer1_m1_paid_status"  value = "{{isset($data['data5']->account_status) ? $data['data5']->account_status : ""}}" placeholder="Closer1 M2 Paid Status" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Setter1 M2 Paid Status</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter1_m2_paid_status" value="{{isset($data['data6']->account_status) ? $data['data6']->account_status : ""}}" placeholder="Setter1 M2 Paid Status" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Setter2 M2 Paid Status</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter2_m2_paid_status" value="{{isset($data['data7']->account_status) ? $data['data7']->account_status : ""}}" placeholder="Setter2 M2 Paid Status" class="form-control"  type="text">
    </div>
  </div>
</div>
      
<div class="form-group">
  <label class="col-md-2 control-label">Closer1 M1 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer1_m1_paid_date	" value="{{isset($data['data8']->account_status) ? $data['data8']->account_status : ""}}" placeholder="Closer1 M1 Paid Date" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- Text input-->
 
<!-- <div class="form-group"> -->
  {{-- <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Setter2 M2 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  {{-- <input name="setter2_m2_paid_date" value="{{$data->setter2_m2_paid_date}}" placeholder="Setter2 M2 Paid Date" class="form-control"  type="text">
    </div>
  </div>
</div> --}}
<div class="form-group">
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Mark Account status </label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="mark_account_status_id" value="{{isset($data->status->account_status) ? $data->status->account_status : "Mark Account status" }}"  placeholder="Mark Account status" class="form-control" type="text">
    </div>
  </div>
<!-- </div> -->

<!-- Text input-->
 
      
<div class="form-group">
  <label class="col-md-2 control-label" style="margin: 16px 94px 3px -43px;">Pid Status</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="pid_status" value="{{$data->pid_status}}" placeholder="Pid Status" class="form-control" type="text" style="
  margin: 21px 30px 10px -31px;">
    </div>
  </div>
 
{{-- {{-- <!-- <div class="form-group"> --> --}}
  
</div>
<div class="form-group">
  <label class="col-md-2 control-label" style="
  margin: 0px -3px 11px 21px;
">Closer1 M1 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer1_m1_paid_date" value="{{isset($data->closer1_m1_paid_date) ? $data->closer1_m1_paid_date : ""}}" placeholder="Closer1 M1 Paid Date" class="form-control" type="text">
    </div>

    
    
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Closer2 M1 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer2_m1_paid_date" value="{{isset($data->closer2_m1_paid_date) ? $data->closer2_m1_paid_date : ""}}" placeholder="Closer2 M1 Paid Date" class="form-control"  type="text">
    </div>
  </div>
</div>
<div class="form-group">
  <label class="col-md-2 control-label" style="
  margin: 0px -3px 11px 21px;
">Setter1 M1 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter1_m1_paid_date" value="{{isset($data->setter1_m1_paid_date) ? $data->setter1_m1_paid_date : ""}}" placeholder="Setter1 M1 Paid Date" class="form-control" type="text">
    </div>
    
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Setter2 M1 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter2_m1_paid_date" value="{{isset($data->setter2_m1_paid_date) ? $data->setter2_m1_paid_date : ""}}" placeholder="Setter2 M1 Paid Date" class="form-control"  type="text">
    </div>
  </div>
</div>
<div class="form-group">
  <label class="col-md-2 control-label" style="
  margin: 0px -3px 11px 21px;
">Closer1 M2 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer1_m2_paid_date" value="{{isset($data->closer1_m2_paid_date) ? $data->closer1_m2_paid_date : ""}}" placeholder="Closer1 M2 Paid Date" class="form-control" type="text">
    </div>
    
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Closer2 M2 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="closer2_m2_paid_date" value="{{isset($data->closer2_m2_paid_date) ? $data->closer2_m2_paid_date : ""}}" placeholder="Closer2 M2 Paid Date" class="form-control"  type="text">
    </div>
  </div>
</div>


<div class="form-group">
  <label class="col-md-2 control-label" style="
  margin: 0px -3px 11px 21px;
">Setter1 M2 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter1_m2_paid_date" value="{{isset($data->setter1_m2_paid_date) ? $data->setter1_m2_paid_date : ""}}" placeholder="Setter1 M2 Paid Date" class="form-control" type="text">
    </div>
    
  </div>
<!-- </div> -->

<!-- Text input-->
 
<!-- <div class="form-group"> -->
  <label class="col-md-2 control-label" style="margin: 3px -2px -7px -76px;">Setter2 M2 Paid Date</label>  
    <div class="col-md-4 inputGroupContainer">
    <div class="input-group">
        {{-- <span class="input-group-addon"><i class="glyphicon glyphicon-home"></i></span> --}}
  <input name="setter2_m2_paid_date" value="{{isset($data->setter2_m2_paid_date) ? $data->setter2_m2_paid_date : ""}}" placeholder="Setter2 M2 Paid Date" class="form-control"  type="text">
    </div>
  </div>
</div>
 <!-- Select Basic -->
  
<!-- Success message -->
{{-- <div class="alert alert-success" role="alert" id="success_message">Success <i class="glyphicon glyphicon-thumbs-up"></i> Thanks for contacting us, we will get back to you shortly.</div> --> --}}

<!-- Button -->
<div class="form-group">
  <label class="col-md-4 control-label"></label>
  <div class="col-md-4">
    {{-- <button type="submit" class="btn btn-warning" >Send <span class="glyphicon glyphicon-send"></span></button> --}}
  </div>
</div>

</fieldset>
</form>
</div>
    </div><!-- /.container -->
<!-- partial -->
  <script src='//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>
<script src='//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js'></script>
<script src='//cdnjs.cloudflare.com/ajax/libs/bootstrap-validator/0.4.5/js/bootstrapvalidator.min.js'></script><script  src="./script.js"></script>

</body>
</html>
