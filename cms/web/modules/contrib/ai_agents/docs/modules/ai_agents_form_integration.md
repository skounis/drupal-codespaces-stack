# AI Agents Form Integration
This module adds forms to the site that allow the generation of Node Content
Types or Entity Fields using the appropriate AI Agent. The forms will allow the
user to review the suggested configuration before it is added to the site.

## Dependencies
1. The AI (Core) module, with at least one configured AI Provided module.
2. The AI Agents module.

Access to create new node Content Types relies on the node module being
installed. Access to create new Fields requires the Field UI module to be
installed.

## Using the module
### Creating new node Content Types
1. Visit `/admin/structure/types`.
2. If you have the correct permissions, you should see a "Generate with AI"
   button.
3. The button opens a form with a number of optional fields:
   1. **Name**: Give your content type a name. If empty, the AI will be asked
      to provide one.
   2. **Prompt**: A prompt to tell the AI about the content type you want to
      generate. May not be required if you upload a context file. If you provide
      a URL for the AI to crawl to provide context, you will need to complete a
      prompt to tell the AI what to do with the URL.
   3. **Context File**: A file containing additional context to pass to the AI
      to explain what is required. If this file contains information about the
      content type, you may not need to complete a separate prompt. This file is
      not necessary if your prompt contains all the relevant information.
   4. **Website**: An optional URL where a similar item of content exists. If
      this is provided, a prompt MUST be added.
4. Once the form has been completed, press the Generate Content Type button. A
   batch process will begin to use the ContentType AI Agent to generate
   configuration for your new content type.
5. When the process finishes, details of the content type will be displayed. If
   you wish to create the content type, you can click the "Generate Content
   Type" button. If not, you can leave the form.

### Creating new field types
1. On any entity that implements the `field_ui_base_route` notation, visit its
   Manage Fields form. If you have the correct permission, you will see a
   "Create with AI" button.
2. The button opens a form with two optional fields:
   1. **Prompt**: A prompt to tell the AI about the field you want to
      generate. May not be required if you upload a context file. Multiple
      fields can be generated with a single prompt.
   2. **Context File**: A file containing additional context to pass to the AI
      to explain what is required. If this file contains information about the
      content type, you may not need to complete a separate prompt. This file is
      not necessary if your prompt contains all the relevant information.
3. Once the form has been completed, press the Generate Field button. A
   batch process will begin to use the FieldType AI Agent to generate
   configuration for your new field.
4. When the process finishes, details of the field will be displayed. If you
   wish to create the content type, you can click the "Generate Field" button.
   If not, you can leave the form.