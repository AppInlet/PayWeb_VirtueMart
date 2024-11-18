# PayWeb_VirtueMart

## Paygate VirtueMart plugin v1.1.0 for VirtueMart v4.2.18

This is the Paygate plugin for VirtueMart. Please feel free to contact the Payfast support team at
support@payfast.help should you require any assistance.

## Installation

1. **Download the Plugin**

    - Visit the [releases page](https://github.com/Paygate/PayWeb_VirtueMart/releases) and
      download [vmpayment.zip](https://github.com/Paygate/PayWeb_VirtueMart/releases/download/v1.1.0/vmpayment.zip).

2. **Install the Plugin**

    - Login as Joomla Admin.
    - Navigate **Side Menu > System > Extensions > Install Extensions.**
        - a) Click **Or browse for file.**
        - b) Select downloaded **vmpayment.zip** from your computer.
        - c) Click upload and install.
    - Navigate **Side Menu > System > Plugins.**
        - a) Search Paygate in the text box at Top Left.
        - b) Click **Enable plugin** for the Paygate plugin shown in the search result list.
    - Navigate **Side Menu > Components > VirtueMart > Payment Methods.**
        - a) Click the **"New"** Button.
        - b) Enter the following:
            - Payment Name = _Paygate_
            - Self Alias = _Paygate_
            - Published = _Yes_
            - Payment Description = _Pay via Paygate_
            - Select Payment Method as _Paygate_
            - Select Currency as _South African Rand_ (or other supported options)
            - Click **Save & Close**.

3. **Configure the Plugin**

    - Click **Paygate** from **Payment Methods** list.
        - a) Click the **Configuration** tab.
        - b) Enter the following fields with relevant information:
            - Test Mode.
            - Paygate ID.
            - Encryption Key.
            - Default Successful Order Status.
            - Default Failed Order Status.
        - c) Click **Save & Close.**

## Collaboration

Please submit pull requests with any tweaks, features or fixes you would like to share.
