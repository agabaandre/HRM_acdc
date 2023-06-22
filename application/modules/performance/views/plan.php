<div class="card-header">
  <div class="row">
    <div class="col-md-12">
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <tr>
            <th colspan="4">
              <h4>A. Personnel Details</h4>
            </th>
          </tr>
          <tr>
            <td><b>Name</b></td>
            <td></td>
            <td><b>Personnel Number</b></td>
            <td>87000098</td>
          </tr>
          <tr>
            <td><b>Position</b></td>
            <td></td>
            <td><b>In this Position since</b></td>
            <td></td>
          </tr>
          <tr>
            <td><b>Directorate/Department</b></td>
            <td></td>
            <td><b>Division/Unit</b></td>
            <td></td>
          </tr>
          <tr>
            <td colspan="4"><b>Current performance period</b></td>
          </tr>
          <tr>
            <td colspan="4">January 2023 - December 2023</td>
          </tr>
          <tr>
            <td colspan="4"><b>Name of direct supervisor</b></td>
          </tr>
          <tr>
            <td colspan="4"></td>
          </tr>
          <tr>
            <td colspan="4"><b>Name of second supervisor</b></td>
          </tr>
          <tr>
            <td colspan="4"></td>
          </tr>
        </table>
      </div>
    </div>

    <div class="col-md-12">
      <!-- Content for the second column goes here -->
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="container mt-5">
      <div class="table-responsive">
        <button id="add-row" type="button" class="btn btn-primary">Add Row</button>
        <table id="objectives" class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>#</th>
              <th style="width:25%;">Objective<br>Statement of the result that needs to be achieved</th>
              <th tyle="width:5%;">Time Line<br>Timeframe within which the result is to be achieved</th>
              <th tyle="width:25%;">Deliverables and KPIs<br>Deliverables - the evidence that the result has been achieved; KPI’s gives an indication of how well the result was achieved</th>
              <th tyle="width:20%;">Weight<br>The total weight of all objectives should be 100%</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><b>1</b></td>
              <td>
                <div class="form-group">
                  <div class="summernote" data-target="objective1"></div>
                </div>
              </td>
              <td>
                <div class="form-group">
                  <input type="date" class="form-control" name="timeline1" required>
                </div>
              </td>
              <td>
                <div class="form-group">
                  <div class="summernote" data-target="deliverable1"></div>
                </div>
              </td>
              <td>
                <div class="form-group">
                  <input type="text" class="form-control" name="weight1" required>
                </div>
              </td>
              <td>
                <div class="form-group">
                  <textarea name="comments1" rows="4" cols="50" class="form-control"></textarea>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>





    <table id="example" class="table table-striped table-bordered">
      <tr>
        <h4>C. Competencies </h4>
      </tr>
      <tr>All staff members shall be assessed against AU Values and Core and Functional Competencies; in
        addition to AU Values and Core and Functional Competencies, staff with Managerial
        responsibilities will also be rated on the Leadership competencies</tr>

      <tr>
        <td colspan="3">
          <h6>
            <center>AU Values<br>Respect for diversity and team work - Think Africa above all -
              Transparency and Accountability - Integrity and Impartiality<br>Efficiency and
              Professionalism - Information and Knowledge sharing</center>
          </h6>
        </td>

      </tr>


      <tr>
        <td>
          <h4>
            <center>Core</center>
          </h4>
        </td>
        <td>
          <h4>
            <center>Functional</center>
          </h4>
        </td>
        <td>
          <h4>
            <center> Leadership</center>
          </h4>
        </td>

      </tr>
      <tr>
        <td>
          <center>Building Relationships</center>
        </td>
        <td>
          <center>Conceptual Thinking and Problem Solving</center>
        </td>
        <td>
          <center> Strategic Perspective</center>
        </td>

      </tr>
      <tr>
        <td>
          <center>Responsibility</center>
        </td>
        <td>
          <center>Job Knowledge </center>
        </td>
        <td>
          <center> Developing Others</center>
        </td>
      </tr>
      <tr>
        <td>
          <center>Learning Orientation</center>
        </td>
        <td>
          <center>Drive for Results</center>
        </td>
        <td>
          <center> Driving Change</center>
        </td>
      </tr>
      <tr>
        <td>
          <center>Communicating with impact</center>
        </td>
        <td>
          <center>Innovative and taking initiative</center>
        </td>
        <td>
          <center> Managing Risk</center>
        </td>
      </tr>

    </table>


    <table id="example" class="table table-striped table-bordered">
      <tr>
        <h4>D. Personal Development Plan </h4>
      </tr>

      <tr>
        <td colspan="2">
          1. Is training recommended for this staff member?
          <select name="is_training" class="form-control">
            <option value="No">No</option>
            <option value="Yes">Yes</option>
          </select>
        </td>


      </tr>
      <tr>
        <td colspan="2">
          2. If yes, in what subject/ skill area(s) is the training recommended for this staff
          member?<br>
          <div class="form-group"><textarea name="subject" rows="4" cols="100"></textarea></div>


        </td>


      </tr>
      <tr>
        <td colspan="2">
          3. How will the recommended training(s) contribute to the staff member’s development and the
          department’s work?<br>
          <div class="form-group"><textarea name="contribution" rows="4" cols="100"></textarea></div>

        </td>
      </tr>
      <tr>
        <td colspan="2">
          4. Selection of courses in line with training needs.<br>
          &nbsp;&nbsp;&nbsp;&nbsp;4.1. With reference to the current AUC Learning and Development
          (L&D) Catalogue, please list the recommended course(s) for this staff member:<br>
          <div class="form-group"><textarea name="courses" rows="4" cols="100"></textarea></div><br>
          &nbsp;&nbsp;&nbsp;&nbsp;4.2. Where applicable, please provide details of highly
          recommendable course(s) for this staff member that are not listed in the AUC L&D
          Catalogue.<br>
          <div class="form-group"><textarea name="details" rows="4" cols="100"></textarea></div>
        </td>
      </tr>

      <tr>
        <td align="right"><button type="submit" name="Save" class="btn btn-primary btn-lg align-right">Save </button></td>
      </tr>
    </table>

  </div>
  </form>

</div>
</div>
</div>
<script>
  $(document).ready(function() {
    var rowCount = 1;

    // Add new row
    $('#add-row').click(function() {
      rowCount++;

      var newRow = '<tr>' +
        '<td><b>' + rowCount + '</b><br><i class = "fa fa-minus remove-row"></i> </td>' +
        '<td><div class="form-group"><div class="summernote" data-target="objective' + rowCount + '"></div></div></td>' +
        '<td><div class="form-group"><input type="date" class="form-control" name="timeline' + rowCount + '" required></div></td>' +
        '<td><div class="form-group"><div class="summernote" data-target="deliverable' + rowCount + '"></div></div></td>' +
        '<td><div class="form-group"><input type="text" class="form-control" name="weight' + rowCount + '" required></div></td>' +
        '<td><div class="form-group"><textarea name="comments' + rowCount + '" rows="4" cols="50" class="form-control"></textarea></div></td>' +
        '</tr>';

      $('#objectives tbody').append(newRow);
      $('.summernote').summernote({
        height: 100
      });
    });

    // Remove row
    $(document).on('click', '.remove-row', function() {
      $(this).closest('tr').remove();
      rowCount--;
    });

    // Initialize Summernote
    $('.summernote').summernote({
      height: 100
    });
  });
</script>