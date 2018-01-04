jQuery( function($) {
var bounce,series_name;        
    $(document).ready(function(){
        
        // do d3js stuff.
        $("#get_report").click(function(){
            var series = $("#series").val(),
                series_name = $("#series").find("option:selected").text();
 
            
            from=($("#series-from").val()!=="")?$("#series-from").val():null;
            to=($("#series-to").val()!=="")?$("#series-to").val():null;
            
            if ($("#posts").length==0 || $("#posts").val()==""){
                
                url = "http://www.btrtoday.com/json/s3/series/"+series;
                
                if (from) {
                    url += "/"+from;
                }
                if (to) {
                    url += "/"+to;
                }
                alert(url);
                getGraph(url,series_name);
            }
            else{
                url = "http://www.btrtoday.com/json/s3/post/"+$("#posts").val()+"/";
                post_title = $("#posts option:selected").text();
                getGraph(url, post_title);
            }
            
        })
        
        $("#clear").click(function(){
            $("#graph").empty();
        })
        
        $("#series").change(function(){
            clearTimeout(bounce);
            bounce = setTimeout(getSeriesPosts(),500);
        });
        
        
    });
    
    var from = $( "#from" )
        .datepicker({
          defaultDate: "-1w",
          changeMonth: true,
          numberOfMonths: 1,
          dateFormat: "yy-mm-dd"
        })
        .on( "change", function() {
          to.datepicker( "option", "minDate", getDate( this ) );
        }),
        
      to = $( "#to" ).datepicker({
        changeMonth: true,
        numberOfMonths: 1,
        dateFormat: "yy-mm-dd"
      })
      .on( "change", function() {
        from.datepicker( "option", "maxDate", getDate( this ) );
      });
      
 
    function getDate( element ) {
      var date, dateFormat = "yy-mm-dd";
      try {
        date = $.datepicker.parseDate( dateFormat, element.value );
      } catch( error ) {
        date = null;
      }
 
      return date;
    }
    
    
    function getSeriesPosts(){
        series = $("#series").val();
        url = "http://www.btrtoday.com/json/series-posts/"+series;
        $.getJSON(url,function(data){
            if ($("#posts").length) {
                $("#posts").remove();
            }
            var posts = $("<select></select>").attr("id", "posts").attr("name","posts");
            posts.append("<option value=''>all posts</option>");
            for(i=0;i<data.length;i++){
                var option = $("<option></option>").attr("value",data[i].id).text(data[i].title);
                posts.append(option);
            }
            $("#series").after(posts);
        });
        
    }
    


  } );





function getGraph(json_url,title) {
    
    document.body.style.cursor = "wait";
    // load the data
    d3.json(json_url, function(error, data) {
        
        document.body.style.cursor = "default";    
        var total = 0;
        
        // converts string to int.
        data.forEach( function(d) {
            d[0] = d[0];
            d[1] = +d[1];
            total += d[1];
        });
        
        var mean = Math.round(total/data.length);
        
        var div =jQuery("<div></div>").attr("overflow-x","scroll").attr("id","text-width").width(1000);
        jQuery("body").append(div);
        
        var yAxisWidth = d3.max(data,function(d){
            var div = jQuery('#text-width');
            div.text(d[0]);
            while (div.width() >= div.get(0).scrollWidth) {
                div.width(div.width()-1);   
            }
            return div.width();
        });
        
         // set the dimensions of the canvas
        var margin = {top: 40, right: 20, bottom:240, left: yAxisWidth},
            width = 600 - margin.left - margin.right,
            height = data.length * 50 - margin.top - margin.bottom;
    
    
         // set the ranges
         var x = d3.scale.linear().range([0, width]);
         var y = d3.scale.ordinal().rangeRoundBands([0, height], .05);
    
        // define the axis
        var xAxis = d3.svg.axis()
            .scale(x)
            .orient("bottom")
            .ticks(10);
    
        var yAxis = d3.svg.axis()
            .scale(y)
            .orient("left")
        
        // add the SVG element
        var svg = d3.select("#graph").append("svg")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
            .append("g")
            .attr("transform", 
                "translate(" + margin.left + "," + margin.top + ")");

         
        // scale the range of the data
        x.domain([0, d3.max(data, function(d) { return d[1]; })]);
        y.domain(data.map(function(d) { return d[0]; }));
        
        // add title text      
        svg.append("text")
            .attr("x", (width / 2))             
            .attr("y", 0 - (margin.top / 2))
            .attr("text-anchor", "middle")  
            .style("font-size", "16px") 
            //.style("text-decoration", "underline")  
            .text(total + " total file requests");
            
      
      // add axes
      svg.append("g")
          .attr("class", "x axis")
          .attr("transform", "translate(0," + height + ")")
          .call(xAxis)
          .append("text")
            .attr("y", 5)
            .attr("dy", "250px")
            .style("text-anchor", "end")
            .text("File Requests");
      
      
      svg.append("g")
            .attr("class", "y axis")
            .call(yAxis)
            .selectAll("text")
            .style("text-anchor", "end")
            .attr("dx", "-.8em")
            .attr("dy", "-.55em")
    
      // Add bar chart
      svg.selectAll("bar")
          .data(data)
            .enter().append("rect")
          .attr("class", "bar")
          
          .attr("y", function(d) { return y(d[0]); })
          .attr("height", y.rangeBand())
          
          .attr("x", 0)
          .attr("width", function(d) { return x(d[1]); })
          
          
          .on("mouseover", function() { tooltip.style("display", null); })
          .on("mouseout", function() { tooltip.style("display", "none"); })
          .on("mousemove", function(d) {
              var xPosition = d3.mouse(this)[0] - 5;
              var yPosition = d3.mouse(this)[1] - 5;
              tooltip.attr("transform", "translate(" + xPosition + "," + yPosition + ")");
              tooltip.select("text").text(d[1]);
          });

          
    // Prep the tooltip bits, initial display is hidden
  var tooltip = svg.append("g")
    .attr("class", "tooltip")
    .style("display", "none");
      
  tooltip.append("rect")
    .attr("width", 60)
    .attr("height", 20)
    .attr("fill", "white")
    .style("opacity", 0.5);

  tooltip.append("text")
    .attr("x", 30)
    .attr("dy", "1.2em")
    .style("text-anchor", "middle")
    .attr("font-size", "12px")
    .attr("font-weight", "bold");          
    });

}


function getbtrTop10Artists_posts() {
    
    
// set the dimensions of the canvas
    var margin = {top: 40, right: 20, bottom:240, left: 40},
        width = 960 - margin.left - margin.right,
        height = 480 - margin.top - margin.bottom;

    
    // set the ranges
    var x = d3.scale.ordinal().rangeRoundBands([0, width], .05);
    
    var y = d3.scale.linear().range([height, 0]);
    
    // define the axis
    var xAxis = d3.svg.axis()
        .scale(x)
        .orient("bottom")
    
    var yAxis = d3.svg.axis()
        .scale(y)
        .orient("left")
        .ticks(10);


    // add the SVG element
    var svg = d3.select("#graph").append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
      .append("g")
        .attr("transform", 
              "translate(" + margin.left + "," + margin.top + ")");
    
    
    // load the data
    d3.json(json_url, function(error, data) {
        var total = 0;
        // converts string to int.
        data.forEach(function(d) {
            d[0] = d[0];
            d[1] = +d[1];
            total += d[1];
        });
        
        
        
      // scale the range of the data
      x.domain(data.map(function(d) { return d[0]; }));
      y.domain([0, d3.max(data, function(d) { return d[1]; })]);
      
        svg.append("text")
            .attr("x", (width / 2))             
            .attr("y", 0 - (margin.top / 2))
            .attr("text-anchor", "middle")  
            .style("font-size", "16px") 
            //.style("text-decoration", "underline")  
            .text(title + ": "+ total + " total file requests");
            
      // add axis
      svg.append("g")
          .attr("class", "x axis")
          .attr("transform", "translate(0," + height + ")")
          .call(xAxis)
        .selectAll("text")
          .style("text-anchor", "end")
          .attr("dx", "-.8em")
          .attr("dy", "-.55em")
          .attr("transform", "rotate(-90)" );
    
      svg.append("g")
          .attr("class", "y axis")
          .call(yAxis)
        .append("text")
          .attr("transform", "rotate(-90)")
          .attr("y", 5)
          .attr("dy", ".71em")
          .style("text-anchor", "end")
          .text("File Requests");
    
    
      // Add bar chart
      svg.selectAll("bar")
          .data(data)
        .enter().append("rect")
          .attr("class", "bar")
          .attr("x", function(d) { return x(d[0]); })
          .attr("width", x.rangeBand())
          .attr("y", function(d) { return y(d[1]); })
          .attr("height", function(d) { return height - y(d[1]); })
          .on("mouseover", function() { tooltip.style("display", null); })
          .on("mouseout", function() { tooltip.style("display", "none"); })
          .on("mousemove", function(d) {
              var xPosition = d3.mouse(this)[0] - 5;
              var yPosition = d3.mouse(this)[1] - 5;
              tooltip.attr("transform", "translate(" + xPosition + "," + yPosition + ")");
              tooltip.select("text").text(d[1]);
          });

          
    // Prep the tooltip bits, initial display is hidden
  var tooltip = svg.append("g")
    .attr("class", "tooltip")
    .style("display", "none");
      
  tooltip.append("rect")
    .attr("width", 60)
    .attr("height", 20)
    .attr("fill", "white")
    .style("opacity", 0.5);

  tooltip.append("text")
    .attr("x", 30)
    .attr("dy", "1.2em")
    .style("text-anchor", "middle")
    .attr("font-size", "12px")
    .attr("font-weight", "bold");          
    });

}

function getbtrTop10Artists_tracks() {
//code
}