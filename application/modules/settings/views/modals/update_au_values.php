              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="update_au_values<?php echo $au->id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit AU Values: <?php //echo $au->description; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/update_content'); ?>
                      <input type="hidden" name="table" value="au_values">
                      <input type="hidden" name="redirect" value="au">
                      <input type="hidden" name="column_name" value="id">
                      <input type="hidden" name="caller_value" value="<?php echo $au->id; ?>">
                      <div class="row">
                        <div class="col-md-6">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Description:</label>
                            <textarea class="form-control" name="description" id="description" required><?php echo $au->description; ?></textarea>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Annotation:</label>
                            <textarea class="form-control" name="annotation" id="annotation" required><?php echo $au->annotation; ?></textarea>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Score 5:</label>
                            <textarea type="text" class="form-control" name="score_5" id="score_5" required><?php echo $au->score_5; ?></textarea>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Score 4:</label>
                            <textarea type="text" class="form-control" name="score_5" id="score_4" required><?php echo $au->score_4; ?></textarea>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Score 3:</label>
                            <textarea type="text" class="form-control" name="score_3" id="score_3" required><?php echo $au->score_3; ?></textarea>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Score 2:</label>
                            <textarea type="text" class="form-control" name="score_2" id="score_5" required><?php echo $au->score_2; ?></textarea>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Score 1:</label>
                            <textarea type="text" class="form-control" name="score_1" id="score_1" required><?php echo $au->score_1; ?></textarea>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Category:</label>
                                <select type="text" name="category" autocomplete="off" placeholder="Category" class="form-control">
                                    <?php //foreach($countries->result() as $coutry): ?>
                                        <option value="<?php echo "Functional"; ?>"><?php echo "Functional" ?></option>
                                        <option value="<?php echo "Leadership"; ?>"><?php echo "Leadership" ?></option>
                                        <option value="<?php echo "Core"; ?>"><?php echo "Core" ?></option>
                                        <option value="<?php echo "Values"; ?>"><?php echo "Values" ?></option>
                                    <?php //endforeach; ?>
                                </select>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Version:</label>
                            <select type="text" name="version" autocomplete="off" placeholder="Version" class="form-control">
                                <?php //foreach($countries->result() as $coutry): ?>
                                    <option value="<?php echo 1; ?>"><?php echo "Version 1.0"; ?></option>
                                    <option value="<?php echo 2; ?>"><?php echo "Version 2.0"; ?></option>
                                    <option value="<?php echo 3; ?>"><?php echo "Version 3.0"; ?></option>
                                    <option value="<?php echo 4; ?>"><?php echo "Version 4.0"; ?></option>
                                    <option value="<?php echo 5; ?>"><?php echo "Version 5.0"; ?></option>
                                <?php //endforeach; ?>
                            </select>
                          </div>
                        </div>

                      </div>

                      <div class="form-group" style="float:right;">
                        <br>
                        <label for="submit"></label>
                        <input type="submit" class="btn btn-dark" value="Submit">
                        <input type="reset" class="btn btn-danger" value="Reset">
                      </div>

                      <?php echo form_close(); ?>
                    </div>
                  </div>
                </div>


              </div>