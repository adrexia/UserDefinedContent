<!-- JS Relys on EditableContentField as a class - and the 3 ids in this order - do not change -->
<section class="$ClassName EditableContentField" data-id="$Name.ATT EditableItem_$Pos $Name">
	<div class="fieldInfo">
		<div class="editable-content-icons pull-left">
		<% if canEdit %>
			<img class="fieldHandler pull-left" src="usercontent/images/move-vertical.png" alt="<% _t('EditableFormField.DRAG', 'Drag to rearrange order of fields') %>" />
		<% end_if %>
		
			<img class="icon pull-left" src="$Icon" alt="$ClassName" title="$singular_name" />
		</div>
	
		<div class="editable-content-title-field pull-left">$TitleField</div>
	</div>
	
	<div class="field-actions">
		<% if showExtraOptions %>
			<a class="moreOptions" href="#" title="<% _t('EditableContentField.SHOWOPTIONS', 'Show Options') %>"><% _t('EditableContentField.SHOWOPTIONS','Show Options') %></a>
		<% end_if %>
		
		<% if canDelete %>
			<a class="delete" href="#" title="<% _t('EditableContentField.DELETE', 'Delete') %>"><% _t('EditableContentField.DELETE', 'Delete') %></a>
		<% end_if %> 	
	</div>
	
	<% if showExtraOptions %>
		<div class="extraOptions hidden" data-id="$Name.ATT-extraOptions">


			<% if FieldConfiguration %>
				<% loop FieldConfiguration %>
					$FieldHolder
				<% end_loop %>
			<% end_if %>
		
		
		</div>
	<% end_if %>
	
	<!-- Hidden option Fields -->
	<input type="hidden" class="typeHidden" name="{$FieldName}[Type]" value="$ClassName" /> 
	<input type="hidden" class="sortHidden" name="{$FieldName}[Sort]" value="$Sort" />
</section>
