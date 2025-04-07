<div class="row">
	<div class="col-md-12">
		<!-- general form elements disabled -->
		<div class="card card-default">
			<div class="card-header">
				
				<hr>
			</div>
			<!-- /.card-header -->

			<div class="card-body">
				<?php echo form_open_multipart(base_url('settings/sysvariables')); ?>
				<?php foreach ($setting as $key => $value) { ?>
					<div id="">
						<label><?php echo strtoupper(str_replace("_", " ", $key)); ?></label>
						<input type="text" class="form-control" name="<?php echo $key; ?>" style="width:100%; padding:10px;" value="<?php echo $value; ?>" if <?php if ($key == 'id') {
																											echo "readonly";
																										} ?>>
					</div>
				<?php  } ?>

				<button class="btn btn-success mt-3" type="submit"><span class="add"></span>Save</button>

				</form>
			</div>
		</div>
		<!-- /.card-body -->
	</div>
</div>
