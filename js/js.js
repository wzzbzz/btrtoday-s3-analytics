jQuery( function($) {
        
    $(document).ready(function(){
        
        // do d3js stuff.
        $("#get_report").click(function(){
            var series = $("#series").val(),
                series_name = $("#series").find("option:selected").text();
                from=$("#from").val(),
                to=$("#to").val();
            if (series == "" || from == "" || to== "") {
                alert("empty value");
                return false;
            }
            getGraph(series,from,to,series_name);
        })
        
        $("#clear").click(function(){
            $("#graph").empty();
        })
        
    });
    
    var from = $( "#from" )
        .datepicker({
          defaultDate: "+1w",
          changeMonth: true,
          numberOfMonths: 1,
          dateFormat: "yy-mm-dd"
        })
        .on( "change", function() {
          to.datepicker( "option", "minDate", getDate( this ) );
        }),
      to = $( "#to" ).datepicker({
        defaultDate: "+1w",
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
        console.log(date);
      } catch( error ) {
        date = null;
      }
 
      return date;
    }
  } );


function getGraph(series,from,to,series_name) {
    // set the dimensions of the canvas
    var margin = {top: 40, right: 20, bottom: 70, left: 40},
        width = 620 - margin.left - margin.right,
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
    d3.json("http://qa.btrtoday.com/json/s3/"+from+"/"+to+"/"+series, function(error, data) {
    
        // converts string to int.
        data.forEach(function(d) {
            d[0] = d[0];
            d[1] = +d[1];
        });
        
        
        
      // scale the range of the data
      x.domain(data.map(function(d) { return d[0]; }));
      y.domain([0, d3.max(data, function(d) { return d[1]; })]);
      
        svg.append("text")
            .attr("x", (width / 2))             
            .attr("y", 0 - (margin.top / 2))
            .attr("text-anchor", "middle")  
            .style("font-size", "16px") 
            .style("text-decoration", "underline")  
            .text(series_name + " " + from + " - " + to);
            
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
          .attr("height", function(d) { return height - y(d[1]); });

    });

}